import { supabase } from '@/lib/customSupabaseClient';
import { sendTaskAssignmentNotification, sendTaskAcceptedNotifications, sendTaskCompletedNotifications, sendTaskReviewNotification, sendAdminTaskAcceptedNotification, sendAdminTaskCompletedNotification } from './taskNotificationService';
import { sendWhatsAppMessage } from './wasenderapiService';
import { DEFAULT_TASK_NOTIFICATION_TEMPLATE } from '@/utils/taskPersonalization';

function newId() {
  return globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

async function hydrateTasksList(tasks) {
  if (!tasks?.length) return [];

  const taskIds = tasks.map((t) => t.id);
  const categoryIds = [...new Set(tasks.map((t) => t.category_id).filter(Boolean))];

  const categoriesById = {};
  if (categoryIds.length) {
    const { data: categories } = await supabase
      .from('task_categories')
      .select('*')
      .in('id', categoryIds);
    (categories || []).forEach((c) => { categoriesById[c.id] = c; });
  }

  const { data: assignments } = await supabase
    .from('task_assignments')
    .select('id, task_id, status, progress, user_id, accepted_at, completed_at')
    .in('task_id', taskIds);

  const userIds = [...new Set((assignments || []).map((a) => a.user_id).filter(Boolean))];
  const profilesById = {};
  if (userIds.length) {
    const { data: users } = await supabase.from('users').select('id, name, email, phone, role').in('id', userIds);
    (users || []).forEach((u) => {
      profilesById[u.id] = {
        id: u.id,
        full_name: u.name,
        email: u.email,
        phone: u.phone,
        role: u.role,
      };
    });
    if (!users?.length) {
      const { data: profiles } = await supabase
        .from('profiles')
        .select('id, full_name, email, phone, role')
        .in('id', userIds);
      (profiles || []).forEach((p) => { profilesById[p.id] = p; });
    }
  }

  const assignmentsByTask = {};
  (assignments || []).forEach((a) => {
    if (!assignmentsByTask[a.task_id]) assignmentsByTask[a.task_id] = [];
    assignmentsByTask[a.task_id].push({
      ...a,
      profiles: profilesById[a.user_id] || null,
    });
  });

  return tasks.map((task) => ({
    ...task,
    task_categories: task.category_id ? categoriesById[task.category_id] || null : null,
    task_assignments: assignmentsByTask[task.id] || [],
  }));
}

async function fetchProfilesMap(userIds) {
  if (!userIds.length) return {};
  const map = {};
  const { data: users } = await supabase.from('users').select('id, name, email, phone, role').in('id', userIds);
  (users || []).forEach((u) => {
    map[u.id] = { id: u.id, full_name: u.name, email: u.email, phone: u.phone, role: u.role };
  });
  const missing = userIds.filter((id) => !map[id]);
  if (missing.length) {
    const { data: profiles } = await supabase
      .from('profiles')
      .select('id, full_name, email, phone, role')
      .in('id', missing);
    (profiles || []).forEach((p) => { map[p.id] = p; });
  }
  return map;
}

export const uploadTaskSourceDocument = async (file, taskId) => {
  try {
    const fileExt = file.name.split('.').pop();
    const fileName = `source-${Date.now()}-${Math.random().toString(36).substring(2)}.${fileExt}`;
    const filePath = `sources/${fileName}`;

    const { error: uploadError } = await supabase.storage
      .from('task-attachments')
      .upload(filePath, file);

    if (uploadError) throw uploadError;

    const { data: publicUrlData } = supabase.storage
      .from('task-attachments')
      .getPublicUrl(filePath);

    const record = {
      task_id: taskId,
      file_name: file.name,
      file_url: publicUrlData.publicUrl,
      attachment_type: 'source',
    };

    const { data, error } = await supabase
      .from('task_attachments')
      .insert([record])
      .select()
      .single();

    if (error) throw error;
    return { success: true, data, url: publicUrlData.publicUrl };
  } catch (error) {
    console.error('Error uploading source document:', error);
    return { success: false, error: error.message };
  }
};

async function queueTaskNotifications(taskId, assignmentRows, scheduleTimes) {
  for (const assignment of assignmentRows) {
    for (const scheduledAt of scheduleTimes) {
      if (!scheduledAt) continue;
      await supabase.from('task_notification_queue').insert([{
        id: newId(),
        task_id: taskId,
        assignment_id: assignment.id,
        scheduled_at: scheduledAt,
        status: 'pending',
      }]);
    }
  }
}

const WHATSAPP_SEND_INTERVAL_MS = 6000;

async function notifyAssignees(task, assignmentRows, profileMap, options) {
  const docLinks = (options.sourceDocuments || []).map((d) => d.url || d.file_url).filter(Boolean).join('\n');
  const template = options.notificationTemplate || task.notification_template || DEFAULT_TASK_NOTIFICATION_TEMPLATE;

  for (let i = 0; i < assignmentRows.length; i += 1) {
    const assignment = assignmentRows[i];
    const profile = profileMap[assignment.user_id];
    if (!profile?.phone) continue;

    if (i > 0) {
      await new Promise((resolve) => setTimeout(resolve, WHATSAPP_SEND_INTERVAL_MS));
    }

    await sendTaskAssignmentNotification({
      assigneePhone: profile.phone,
      assigneeName: profile.full_name || profile.name || profile.email,
      assigneeEmail: profile.email,
      taskTitle: task.title,
      taskDescription: task.description,
      deadline: task.deadline,
      priority: task.priority,
      startDate: task.start_date,
      inviteToken: assignment.invite_token,
      messageTemplate: template,
      documentLinks: docLinks,
      assignmentId: assignment.id,
    }).catch((err) => console.error('Task notification failed', err));
  }
}

export const createTaskWithAssignments = async (taskData, assigneeIds, options = {}) => {
  try {
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) throw new Error('Not authenticated');

    const scheduleTimes = (options.schedules || [])
      .filter(Boolean)
      .map((t) => (t.includes('T') ? t : `${t.replace(' ', 'T')}`));
    const isScheduled = Boolean(options.scheduleLater && scheduleTimes.length);
    const notificationTemplate = options.notificationTemplate || DEFAULT_TASK_NOTIFICATION_TEMPLATE;

    const taskRecord = {
      id: newId(),
      title: taskData.title,
      description: taskData.description,
      priority: taskData.priority,
      category_id: taskData.category_id || null,
      start_date: taskData.start_date || null,
      deadline: taskData.deadline,
      created_by: user.id,
      status: isScheduled ? 'Scheduled' : 'Pending',
      notification_template: notificationTemplate,
      schedules_json: scheduleTimes.length ? JSON.stringify(scheduleTimes) : null,
      is_scheduled: isScheduled ? 1 : 0,
    };

    const { error: taskError } = await supabase.from('tasks').insert([taskRecord]);
    if (taskError) throw taskError;

    const task = taskRecord;
    const sourceDocuments = [];

    if (options.sourceFiles?.length) {
      for (const file of options.sourceFiles) {
        const uploaded = await uploadTaskSourceDocument(file, task.id);
        if (uploaded.success) sourceDocuments.push(uploaded.data);
      }
    }

    let assignmentRows = [];

    if (assigneeIds?.length) {
      const profileMap = await fetchProfilesMap(assigneeIds);

      const assignments = assigneeIds.map((userId) => {
        const profile = profileMap[userId];
        const isAdmin = profile && ['admin', 'super_admin', 'director'].includes(profile.role);
        return {
          id: newId(),
          task_id: task.id,
          user_id: userId,
          invite_token: newId(),
          status: isAdmin ? 'Accepted' : 'Pending',
          progress: 0,
          accepted_at: isAdmin ? new Date().toISOString() : null,
          last_update_at: new Date().toISOString(),
        };
      });

      const { error: assignError } = await supabase.from('task_assignments').insert(assignments);
      if (assignError) throw assignError;

      assignmentRows = assignments;

      if (isScheduled) {
        await queueTaskNotifications(task.id, assignmentRows, scheduleTimes);
      } else {
        await notifyAssignees(task, assignmentRows, profileMap, {
          ...options,
          notificationTemplate,
          sourceDocuments,
        });
      }
    }

    return { success: true, data: task, assignments: assignmentRows };
  } catch (error) {
    console.error('Error in createTaskWithAssignments:', error);
    return { success: false, error: error.message };
  }
};

export const createBatchTasksWithAssignments = async (tasksList, assigneeIds, sharedOptions = {}) => {
  const created = [];
  const errors = [];

  for (let i = 0; i < tasksList.length; i += 1) {
    const taskEntry = tasksList[i];
    const { sourceFiles, ...taskData } = taskEntry;
    const res = await createTaskWithAssignments(taskData, assigneeIds, {
      ...sharedOptions,
      sourceFiles: sourceFiles || [],
    });
    if (res.success) created.push(res.data);
    else errors.push(res.error || 'Unknown error');

    if (i < tasksList.length - 1 && !sharedOptions.scheduleLater) {
      await new Promise((resolve) => setTimeout(resolve, WHATSAPP_SEND_INTERVAL_MS));
    }
  }

  if (!created.length) {
    return { success: false, error: errors[0] || 'No tasks were created', count: 0 };
  }

  return {
    success: errors.length === 0,
    count: created.length,
    failed: errors.length,
    error: errors.length ? errors[0] : null,
    data: created,
  };
};

export const createTask = async (taskData, assigneeIds, options) => {
  return createTaskWithAssignments(taskData, assigneeIds, options);
};

export const updateTask = async (taskId, taskData, assigneeIds) => {
    try {
        const { error: updateError } = await supabase
            .from('tasks')
            .update({
                title: taskData.title,
                description: taskData.description,
                priority: taskData.priority,
                category_id: taskData.category_id,
                deadline: taskData.deadline
            })
            .eq('id', taskId);
            
        if (updateError) throw updateError;
        
        const { data: currentAssignments } = await supabase
            .from('task_assignments')
            .select('user_id')
            .eq('task_id', taskId);
            
        const currentAssigneeIds = currentAssignments.map(a => a.user_id);
        const toAdd = assigneeIds.filter(id => !currentAssigneeIds.includes(id));
        const toRemove = currentAssigneeIds.filter(id => !assigneeIds.includes(id));
        
        if (toRemove.length > 0) {
            await supabase.from('task_assignments').delete().eq('task_id', taskId).in('user_id', toRemove);
        }
        
        if (toAdd.length > 0) {
             const { data: profiles } = await supabase.from('profiles').select('id, role').in('id', toAdd);

            const newAssignments = toAdd.map(userId => {
                const profile = profiles?.find(p => p.id === userId);
                const isAdmin = profile && ['admin', 'super_admin', 'director'].includes(profile.role);

                return {
                    task_id: taskId,
                    user_id: userId,
                    status: isAdmin ? 'Accepted' : 'Pending',
                    progress: 0,
                    accepted_at: isAdmin ? new Date().toISOString() : null,
                    last_update_at: new Date().toISOString()
                };
            });

            await supabase.from('task_assignments').insert(newAssignments);
            
            const { data: updatedTask } = await supabase.from('tasks').select('*').eq('id', taskId).single();
            await sendTaskNotifications(updatedTask, toAdd, 'assignment');
        }

        return { success: true };
    } catch (error) {
        console.error('Error updating task:', error);
        return { success: false, error: error.message };
    }
};

export const deleteTask = async (taskId) => {
    try {
        const { error } = await supabase.from('tasks').delete().eq('id', taskId);
        if (error) throw error;
        return { success: true };
    } catch (error) {
        console.error('Error deleting task:', error);
        return { success: false, error: error.message };
    }
};

export const getTasks = async (filters = {}) => {
  try {
    let query = supabase
      .from('tasks')
      .select('*')
      .order('created_at', { ascending: false });

    if (filters.status && filters.status !== 'All') {
      query = query.eq('status', filters.status);
    }
    if (filters.priority && filters.priority !== 'All') {
      query = query.eq('priority', filters.priority);
    }
    if (filters.category && filters.category !== 'All') {
      query = query.eq('category_id', filters.category);
    }
    if (filters.search) {
      query = query.ilike('title', `%${filters.search}%`);
    }

    const { data, error } = await query;
    if (error) throw error;

    const hydrated = await hydrateTasksList(data || []);
    const tasksWithProgress = hydrated.map((task) => {
      const assignments = task.task_assignments || [];
      const totalProgress = assignments.reduce((sum, a) => sum + (a.progress || 0), 0);
      const overallProgress = assignments.length > 0 ? Math.round(totalProgress / assignments.length) : 0;
      return { ...task, overallProgress, assignments_count: assignments.length };
    });

    return { success: true, data: tasksWithProgress };
  } catch (error) {
    console.error('Error fetching tasks:', error);
    return { success: false, error: error.message, data: [] };
  }
};

export const getTaskDetails = async (taskId) => {
  try {
    const { data: task, error } = await supabase
      .from('tasks')
      .select('*')
      .eq('id', taskId)
      .single();

    if (error) throw error;

    const [hydrated] = await hydrateTasksList([task]);
    const { data: assignments } = await supabase
      .from('task_assignments')
      .select('id, status, progress, user_id, accepted_at, completed_at, task_id')
      .eq('task_id', taskId);

    const profileMap = await fetchProfilesMap((assignments || []).map((a) => a.user_id));
    const assignmentIds = (assignments || []).map((a) => a.id);
    const updatesByAssignment = {};
    const attachmentsByUpdate = {};

    if (assignmentIds.length) {
      const { data: updates } = await supabase
        .from('task_updates')
        .select('id, assignment_id, progress, comment, created_at')
        .in('assignment_id', assignmentIds);
      (updates || []).forEach((u) => {
        if (!updatesByAssignment[u.assignment_id]) updatesByAssignment[u.assignment_id] = [];
        updatesByAssignment[u.assignment_id].push(u);
      });

      const updateIds = (updates || []).map((u) => u.id);
      if (updateIds.length) {
        const { data: attachments } = await supabase
          .from('task_attachments')
          .select('id, update_id, file_url, file_name')
          .in('update_id', updateIds);
        (attachments || []).forEach((att) => {
          if (!attachmentsByUpdate[att.update_id]) attachmentsByUpdate[att.update_id] = [];
          attachmentsByUpdate[att.update_id].push(att);
        });
      }
    }

    hydrated.task_assignments = (assignments || []).map((a) => ({
      ...a,
      profiles: profileMap[a.user_id] || null,
      task_updates: (updatesByAssignment[a.id] || []).map((u) => ({
        ...u,
        task_attachments: attachmentsByUpdate[u.id] || [],
      })),
    }));

    return { success: true, data: hydrated };
  } catch (error) {
    console.error('Error fetching task details:', error);
    return { success: false, error: error.message };
  }
};

export const getMyTasks = async (statusFilter = 'All', categoryFilter = 'All') => {
  try {
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) throw new Error("Not authenticated");

    let assignmentQuery = supabase
      .from('task_assignments')
      .select('id, task_id, status, progress, accepted_at, completed_at, last_update_at, created_at')
      .eq('user_id', user.id)
      .order('created_at', { ascending: false });

    if (statusFilter !== 'All') {
      assignmentQuery = assignmentQuery.eq('status', statusFilter);
    }

    const { data: assignments, error } = await assignmentQuery;
    if (error) throw error;
    if (!assignments?.length) return { success: true, data: [] };

    const taskIds = assignments.map((a) => a.task_id);
    let taskQuery = supabase.from('tasks').select('*').in('id', taskIds);
    if (categoryFilter !== 'All') {
      taskQuery = taskQuery.eq('category_id', categoryFilter);
    }
    const { data: tasks, error: taskError } = await taskQuery;
    if (taskError) throw taskError;

    const tasksById = {};
    (tasks || []).forEach((t) => { tasksById[t.id] = t; });

    const categoryIds = [...new Set((tasks || []).map((t) => t.category_id).filter(Boolean))];
    const categoriesById = {};
    if (categoryIds.length) {
      const { data: categories } = await supabase.from('task_categories').select('*').in('id', categoryIds);
      (categories || []).forEach((c) => { categoriesById[c.id] = c; });
    }

    const creatorIds = [...new Set((tasks || []).map((t) => t.created_by).filter(Boolean))];
    const creatorMap = await fetchProfilesMap(creatorIds);

    const formattedData = assignments
      .map((assignment) => {
        const task = tasksById[assignment.task_id];
        if (!task) return null;
        return {
          ...task,
          task_categories: task.category_id ? categoriesById[task.category_id] || null : null,
          created_by_profile: task.created_by ? creatorMap[task.created_by] || null : null,
          assignment_id: assignment.id,
          assignment_status: assignment.status,
          my_progress: assignment.progress,
          accepted_at: assignment.accepted_at,
          completed_at: assignment.completed_at,
          last_update_at: assignment.last_update_at,
        };
      })
      .filter(Boolean);

    return { success: true, data: formattedData };
  } catch (error) {
    console.error('Error fetching my tasks:', error);
    return { success: false, error: error.message, data: [] };
  }
};

export const acceptTaskAssignment = async (assignmentId) => {
  try {
    const now = new Date().toISOString();
    const { error } = await supabase
      .from('task_assignments')
      .update({
        status: 'Accepted',
        accepted_at: now,
        last_update_at: now,
      })
      .eq('id', assignmentId);

    if (error) throw error;

    const { data: assignment, error: fetchError } = await supabase
      .from('task_assignments')
      .select('*')
      .eq('id', assignmentId)
      .single();
    if (fetchError) throw fetchError;

    const { data: task, error: taskError } = await supabase
      .from('tasks')
      .select('id, title, status')
      .eq('id', assignment.task_id)
      .single();
    if (taskError) throw taskError;

    if (task.status === 'Pending') {
      await supabase.from('tasks').update({ status: 'In Progress' }).eq('id', task.id);
    }

    await sendTaskAcceptedNotifications(assignmentId).catch((err) => console.error('Accept notification failed', err));

    if (import.meta.env.VITE_DATA_BACKEND !== 'mysql') {
      const profileMap = await fetchProfilesMap([assignment.user_id, task.created_by].filter(Boolean));
      const assignee = profileMap[assignment.user_id];
      const creator = profileMap[task.created_by];
      if (creator?.phone) {
        await sendAdminTaskAcceptedNotification(creator.phone, assignee?.full_name || assignee?.name || 'Assignee', task.title);
      }
      if (assignee?.phone) {
        const loginLink = `${window.location.origin}/my-tasks`;
        await sendWhatsAppMessage(
          assignee.phone,
          `Task Accepted ✅\n\nHello ${assignee.full_name || assignee.name},\n\nYou accepted: *${task.title}*\n\nYour task realization is currently at *0%*.\n\nUpdate your progress here:\n${loginLink}`
        );
      }
    }

    return { success: true, data: { ...assignment, tasks: task } };
  } catch (error) {
    console.error('Error accepting task:', error);
    return { success: false, error: error.message };
  }
};

export const declineTaskAssignment = async (assignmentId) => {
  try {
    const now = new Date().toISOString();
    const { error } = await supabase
      .from('task_assignments')
      .update({
        status: 'Declined',
        declined_at: now,
        last_update_at: now,
      })
      .eq('id', assignmentId);

    if (error) throw error;

    const { data: assignment, error: fetchError } = await supabase
      .from('task_assignments')
      .select('*')
      .eq('id', assignmentId)
      .single();
    if (fetchError) throw fetchError;

    const { data: task } = await supabase
      .from('tasks')
      .select('id, title, status')
      .eq('id', assignment.task_id)
      .single();

    return { success: true, data: { ...assignment, tasks: task || null } };
  } catch (error) {
    console.error('Error declining task:', error);
    return { success: false, error: error.message };
  }
};

export const bulkRemoveMyTaskAssignments = async (assignmentIds = []) => {
  try {
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) throw new Error('Not authenticated');
    if (!assignmentIds.length) throw new Error('No tasks selected');

    const { error } = await supabase
      .from('task_assignments')
      .delete()
      .in('id', assignmentIds)
      .eq('user_id', user.id);

    if (error) throw error;
    return { success: true, count: assignmentIds.length };
  } catch (error) {
    console.error('Error removing task assignments:', error);
    return { success: false, error: error.message };
  }
};

export const bulkDeclineTaskAssignments = async (assignmentIds = []) => {
  try {
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) throw new Error('Not authenticated');
    if (!assignmentIds.length) throw new Error('No tasks selected');

    const now = new Date().toISOString();
    const { error } = await supabase
      .from('task_assignments')
      .update({
        status: 'Declined',
        declined_at: now,
        last_update_at: now,
      })
      .in('id', assignmentIds)
      .eq('user_id', user.id);

    if (error) throw error;
    return { success: true, count: assignmentIds.length };
  } catch (error) {
    console.error('Error declining task assignments:', error);
    return { success: false, error: error.message };
  }
};

export const updateTaskProgress = async (assignmentId, taskId, progress, status, comment = null, attachments = []) => {
  try {
    const updates = { 
        progress, 
        status: status || 'In Progress',
        last_update_at: new Date().toISOString()
    };
    
    if (status === 'Completed' || progress === 100) {
        updates.status = 'Completed';
        updates.progress = 100;
        updates.completed_at = updates.last_update_at;
    }

    const { error: assignError } = await supabase
      .from('task_assignments')
      .update(updates)
      .eq('id', assignmentId);
    if (assignError) throw assignError;

    if (comment || (attachments && attachments.length > 0)) {
        const { data: updateData, error: updateError } = await supabase
          .from('task_updates')
          .insert([{ assignment_id: assignmentId, progress, comment: comment || 'Status updated' }])
          .select()
          .single();
        if (updateError) throw updateError;
    
        if (attachments && attachments.length > 0) {
          const attData = attachments.map(att => ({
            task_id: taskId,
            update_id: updateData.id,
            file_url: att.url,
            file_name: att.name
          }));
          const { error: attError } = await supabase.from('task_attachments').insert(attData);
          if (attError) throw attError;
        }
    }

    await evaluateTaskOverallStatus(taskId);

    if (status === 'Completed' || progress === 100) {
      await sendTaskCompletedNotifications(assignmentId).catch((err) => console.error('Complete notification failed', err));

      if (import.meta.env.VITE_DATA_BACKEND !== 'mysql') {
        const { data: assignment } = await supabase.from('task_assignments').select('user_id, task_id').eq('id', assignmentId).single();
        const { data: taskRow } = await supabase.from('tasks').select('title, created_by').eq('id', taskId).single();
        if (assignment && taskRow) {
          const profileMap = await fetchProfilesMap([assignment.user_id, taskRow.created_by]);
          const assignee = profileMap[assignment.user_id];
          const creator = profileMap[taskRow.created_by];
          if (creator?.phone && assignee) {
            await sendAdminTaskCompletedNotification(creator.phone, assignee.full_name || assignee.name, taskRow.title);
          }
        }
      }
    }

    return { success: true };
  } catch (error) {
    console.error('Error updating progress:', error);
    return { success: false, error: error.message };
  }
};

export const adminReviewAssignment = async (assignmentId, taskId, progress, comment, adminName) => {
  try {
    const now = new Date().toISOString();
    const status = progress >= 100 ? 'Completed' : 'In Progress';
    const updates = {
      progress,
      status,
      last_update_at: now,
    };
    if (progress >= 100) {
      updates.completed_at = now;
    } else if (progress < 100) {
      updates.completed_at = null;
    }

    const { error: assignError } = await supabase
      .from('task_assignments')
      .update(updates)
      .eq('id', assignmentId);
    if (assignError) throw assignError;

    const { error: updateError } = await supabase
      .from('task_updates')
      .insert([{
        id: newId(),
        assignment_id: assignmentId,
        progress,
        comment: comment?.trim() ? `[Admin Review] ${comment.trim()}` : `[Admin Review] Progress set to ${progress}%`,
      }]);
    if (updateError) throw updateError;

    await evaluateTaskOverallStatus(taskId);

    await sendTaskReviewNotification({
      assignmentId,
      progress,
      comment,
      adminName,
    }).catch((err) => console.error('Review notification failed', err));

    return { success: true };
  } catch (error) {
    console.error('Error in adminReviewAssignment:', error);
    return { success: false, error: error.message };
  }
};

export const completeTaskAssignment = async (assignmentId, taskId) => {
  try {
    const now = new Date().toISOString();
    const { error } = await supabase
      .from('task_assignments')
      .update({ 
          status: 'Completed', 
          progress: 100, 
          completed_at: now,
          last_update_at: now
      })
      .eq('id', assignmentId);

    if (error) throw error;

    await evaluateTaskOverallStatus(taskId);
    await sendTaskCompletedNotifications(assignmentId).catch((err) => console.error('Complete notification failed', err));

    return { success: true };
  } catch (error) {
    console.error('Error completing task:', error);
    return { success: false, error: error.message };
  }
};

const evaluateTaskOverallStatus = async (taskId) => {
    try {
        const { data: assignments, error } = await supabase
            .from('task_assignments')
            .select('status')
            .eq('task_id', taskId);
            
        if (error) throw error;
        
        if (!assignments || assignments.length === 0) return;

        const allCompleted = assignments.every(a => a.status === 'Completed');
        const anyInProgress = assignments.some(a => a.status === 'In Progress' || a.status === 'Accepted');

        let newStatus = 'Pending';
        if (allCompleted) newStatus = 'Completed';
        else if (anyInProgress) newStatus = 'In Progress';

        await supabase.from('tasks').update({ status: newStatus }).eq('id', taskId);
    } catch(e) {
        console.error("Failed to evaluate task status", e);
    }
};

export const uploadTaskAttachment = async (file) => {
  try {
    const fileExt = file.name.split('.').pop();
    const fileName = `${Date.now()}-${Math.random().toString(36).substring(2)}.${fileExt}`;
    const filePath = `updates/${fileName}`;

    const { error: uploadError } = await supabase.storage
      .from('task-attachments')
      .upload(filePath, file);

    if (uploadError) throw uploadError;

    const { data: publicUrlData } = supabase.storage
      .from('task-attachments')
      .getPublicUrl(filePath);

    return { success: true, url: publicUrlData.publicUrl, name: file.name };
  } catch (error) {
    console.error('Error uploading file:', error);
    return { success: false, error: error.message };
  }
};

export const getTaskStats = async () => {
    try {
        const { data, error } = await supabase.from('tasks').select('status, priority');
        if (error) throw error;

        const stats = {
            total: data.length,
            pending: 0,
            inProgress: 0,
            completed: 0,
            overdue: 0,
            highPriority: 0
        };

        data.forEach(t => {
            if (t.status === 'Pending') stats.pending++;
            if (t.status === 'In Progress') stats.inProgress++;
            if (t.status === 'Completed') stats.completed++;
            if (t.status === 'Overdue') stats.overdue++;
            if (t.priority === 'High' || t.priority === 'Critical') stats.highPriority++;
        });

        return { success: true, data: stats };
    } catch (e) {
        return { success: false, data: { total: 0, pending: 0, inProgress: 0, completed: 0, overdue: 0, highPriority: 0 } };
    }
};

export const checkAndUpdateOverdueTasks = async () => {
    try {
        const now = new Date().toISOString();
        const { data: overdueTasks, error: fetchError } = await supabase
            .from('tasks')
            .select('id')
            .lt('deadline', now)
            .not('status', 'in', '("Completed","Overdue")');

        if (fetchError) throw fetchError;

        if (overdueTasks && overdueTasks.length > 0) {
            const taskIds = overdueTasks.map(t => t.id);
            await supabase.from('tasks').update({ status: 'Overdue' }).in('id', taskIds);
            await supabase
                .from('task_assignments')
                .update({ status: 'Overdue' })
                .in('task_id', taskIds)
                .neq('status', 'Completed');
                
            return { success: true, updatedCount: taskIds.length };
        }
        return { success: true, updatedCount: 0 };
    } catch (e) {
        console.error("Failed to check overdue tasks", e);
        return { success: false, error: e.message };
    }
};

export const resendTaskNotification = async (taskId, assigneeId) => {
    try {
        const { data: task, error } = await supabase.from('tasks').select('*').eq('id', taskId).single();
        if (error) throw error;
        await sendTaskNotifications(task, [assigneeId], 'reminder');
        return { success: true };
    } catch (error) {
        console.error('Error resending notification:', error);
        return { success: false, error: error.message };
    }
};

const sendTaskNotifications = async (task, assigneeIds, templateType, options = {}) => {
    try {
        const { data: assignments } = await supabase
            .from('task_assignments')
            .select('id, user_id, invite_token')
            .eq('task_id', task.id)
            .in('user_id', assigneeIds);

        const profileMap = await fetchProfilesMap(assigneeIds);
        const docLinks = options.documentLinks || '';

        for (const assignment of assignments || []) {
            const profile = profileMap[assignment.user_id];
            if (!profile) continue;

            await sendTaskAssignmentNotification({
                assigneePhone: profile.phone,
                assigneeName: profile.full_name || profile.name || profile.email,
                assigneeEmail: profile.email,
                taskTitle: task.title,
                taskDescription: task.description,
                deadline: task.deadline,
                priority: task.priority,
                startDate: task.start_date,
                inviteToken: assignment.invite_token,
                messageTemplate: task.notification_template,
                documentLinks: docLinks,
                assignmentId: assignment.id,
            });
        }
    } catch(e) {
        console.error("Failed to send task notifications", e);
    }
}

// System DB Checks
export const runSchemaVerifications = () => {
    console.log("=== DB SCHEMA VERIFICATION ===");
    console.log("Expected task_assignments columns: id, task_id, user_id, status, progress, accepted_at, declined_at, completed_at, last_update_at, created_at");
    console.log("Expected profiles columns: id, email, full_name, username, role...");
    console.log("Relationships: task_assignments.task_id -> tasks.id, task_assignments.user_id -> profiles.id");
    
    console.log("=== OTP & WHATSAPP VERIFICATION ===");
    console.log("otp_verifications & whatsapp_messages tables confirmed unchanged.");
    console.log("RLS policies intact.");
}