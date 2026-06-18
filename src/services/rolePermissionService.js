// Service for handling Role-Based Access Control

import { supabase } from '@/lib/customSupabaseClient';
import { findRoleNameForSlug } from '@/config/permissionCatalog';

const useMysql = import.meta.env.VITE_DATA_BACKEND === 'mysql';

const ROLES_KEY = 'ab_roles_v2';
const DEFAULT_PERMISSIONS = [
  'DASHBOARD_VIEW',
  'TIMESHEETS_VIEW', 'TIMESHEETS_CREATE', 'TIMESHEETS_EDIT',
  'ACTIVITIES_CREATE', 'ACTIVITIES_EDIT', 'ACTIVITIES_DELETE',
  'FILL_TIMESHEET_CREATE', 'FILL_TIMESHEET_EDIT',
  'USERS_VIEW', 'USERS_CREATE', 'USERS_EDIT', 'USERS_DELETE',
  'ROLES_VIEW', 'ROLES_CREATE', 'ROLES_EDIT', 'ROLES_DELETE',
  'NOTIFICATIONS_VIEW', 'NOTIFICATIONS_CREATE', 'NOTIFICATIONS_EDIT', 'NOTIFICATIONS_DELETE',
  'SEND_LETTERS_VIEW', 'SEND_LETTERS_CREATE', 'SEND_LETTERS_EDIT', 'SEND_LETTERS_DELETE',
  'TEMPLATES_VIEW', 'TEMPLATES_CREATE', 'TEMPLATES_EDIT', 'TEMPLATES_DELETE',
  'HISTORY_VIEW', 'REPORTS_VIEW',
  'COURSES_VIEW', 'COURSES_CREATE', 'COURSES_EDIT', 'COURSES_DELETE',
  'REGISTRATION_VIEW', 'REGISTRATION_CREATE', 'REGISTRATION_EDIT', 'REGISTRATION_DELETE',
  'QUOTES_VIEW', 'QUOTES_CREATE',
  // New Permissions
  'MEMBERS_ADD', 'MEMBERS_EDIT', 'MEMBERS_DELETE', 'MEMBERS_VIEW',
  'SHAREHOLDERS_ADD', 'SHAREHOLDERS_EDIT', 'SHAREHOLDERS_DELETE', 'SHAREHOLDERS_VIEW',
  'SETTINGS_VIEW', 'SETTINGS_EDIT',
  'EVENTS_ADD', 'EVENTS_EDIT', 'EVENTS_DELETE', 'EVENTS_VIEW',
  'menu.dashboard', 'menu.events', 'menu.invitations', 'menu.event_templates',
  'menu.tasks', 'menu.jobs', 'menu.users', 'menu.members', 'menu.shareholders',
  'menu.courses', 'menu.announcements', 'menu.timesheets', 'menu.operations',
  'menu.system', 'menu.roles',
];

const DEFAULT_ROLES = [
  {
    id: 'role-super-admin',
    name: 'Super Admin',
    description: 'Full system access',
    is_default: true,
    permissions: DEFAULT_PERMISSIONS // All permissions
  },
  {
    id: 'role-manager',
    name: 'Manager',
    description: 'Operational management',
    is_default: true,
    permissions: [
      'DASHBOARD_VIEW',
      'TIMESHEETS_VIEW', 'TIMESHEETS_CREATE', 'TIMESHEETS_EDIT',
      'REPORTS_VIEW',
      'USERS_VIEW',
      'EVENTS_VIEW', 'EVENTS_CREATE', 'EVENTS_EDIT',
      'COURSES_VIEW', 'REGISTRATION_VIEW',
      'MEMBERS_VIEW', 'SHAREHOLDERS_VIEW',
      'EVENTS_VIEW'
    ]
  },
  {
    id: 'role-employee',
    name: 'Employee',
    description: 'Standard access',
    is_default: true,
    permissions: [
      'DASHBOARD_VIEW',
      'FILL_TIMESHEET_CREATE', 'FILL_TIMESHEET_EDIT'
    ]
  },
  {
    id: 'role-customer',
    name: 'Customer',
    description: 'Customer contact — confirms via WhatsApp OTP, no admin access',
    is_default: true,
    permissions: [
      'DASHBOARD_VIEW'
    ]
  },
  {
    id: 'role-task-assignee',
    name: 'Task Assignee',
    description: 'Guest assignee — can only view and accept assigned tasks',
    is_default: true,
    permissions: [
      'DASHBOARD_VIEW'
    ]
  }
];

const initRoles = () => {
  if (!localStorage.getItem(ROLES_KEY)) {
    localStorage.setItem(ROLES_KEY, JSON.stringify(DEFAULT_ROLES));
  }
};

const getRoles = () => {
  initRoles();
  const stored = JSON.parse(localStorage.getItem(ROLES_KEY) || '[]');

  // Ensure newly-added system default roles (e.g. Customer, Task Assignee)
  // always appear even if the user already had a roles list saved.
  const existingNames = new Set(stored.map((r) => String(r.name).toLowerCase()));
  let changed = false;
  for (const def of DEFAULT_ROLES) {
    if (!existingNames.has(def.name.toLowerCase())) {
      stored.push({ ...def });
      changed = true;
    }
  }
  if (changed) saveRoles(stored);

  return stored;
};

const saveRoles = (roles) => {
  localStorage.setItem(ROLES_KEY, JSON.stringify(roles));
};

export const getAllRolePermissions = async () => {
  return getRoles();
};

export const createRoleWithPermissions = async (roleName, description, permissions) => {
  const roles = getRoles();
  const newRole = {
    id: `role-${Date.now()}`,
    name: roleName,
    description: description || '',
    is_default: false,
    permissions: permissions || [],
    created_at: new Date().toISOString()
  };
  roles.push(newRole);
  saveRoles(roles);
  return newRole;
};

export const updateRolePermissions = async (roleId, permissions) => {
  const roles = getRoles();
  const index = roles.findIndex(r => r.id === roleId);
  if (index === -1) throw new Error("Role not found");
  
  if (roles[index].id === 'role-super-admin') {
      roles[index].permissions = DEFAULT_PERMISSIONS;
  } else {
      roles[index].permissions = permissions;
  }
  
  saveRoles(roles);
  return roles[index];
};

export const getUserPermissions = async (userId) => {
  if (useMysql) {
    return getUserPermissionsFromDb(userId);
  }

  const users = JSON.parse(localStorage.getItem('alpha_users') || '[]');
  const user = users.find(u => u.id === userId);

  if (!user) {
    if (userId === 'admin-master') return DEFAULT_PERMISSIONS;
    return [];
  }

  const roles = getRoles();
  const role = roles.find(r => r.id === user.role_id);
  return role ? role.permissions : [];
};

async function getUserPermissionsFromDb(userId) {
  if (!userId) return [];

  try {
    const roleNames = new Set();

    const { data: assignments, error: assignmentError } = await supabase
      .from('user_roles')
      .select('role')
      .eq('user_id', userId);

    if (assignmentError) throw assignmentError;

    for (const row of assignments || []) {
      if (row?.role) roleNames.add(String(row.role).trim());
    }

    if (!roleNames.size) {
      const { data: profile } = await supabase
        .from('profiles')
        .select('role')
        .eq('id', userId)
        .maybeSingle();

      const { data: roles } = await supabase.from('roles').select('id, name');
      const mapped = findRoleNameForSlug(profile?.role, roles || []);
      if (mapped) roleNames.add(mapped);
    }

    if (!roleNames.size) return [];

    const permissions = new Set();
    for (const roleName of roleNames) {
      const { data: rows, error } = await supabase
        .from('role_permissions')
        .select('permission')
        .eq('role', roleName);

      if (error) throw error;

      for (const row of rows || []) {
        const permission = typeof row === 'string' ? row : row?.permission;
        if (permission) permissions.add(permission);
      }
    }

    return [...permissions];
  } catch (error) {
    console.error('[rolePermissionService] getUserPermissionsFromDb:', error);
    return [];
  }
}

export const hasPermission = async (userId, permissionKey) => {
    const permissions = await getUserPermissions(userId);
    return permissions.includes(permissionKey);
};

export const getPermissionsByRole = async (roleId) => {
    const roles = getRoles();
    const role = roles.find(r => r.id === roleId);
    return role ? role.permissions : [];
};

export const PERMISSION_KEYS = DEFAULT_PERMISSIONS;