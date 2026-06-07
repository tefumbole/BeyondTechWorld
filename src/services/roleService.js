import { supabase } from '@/lib/customSupabaseClient';
import { getAllUsers, createUser, updateUser } from '@/services/userService';
import {
  findRoleNameForSlug,
  getPermissionsByCategory,
  getAllPermissionIds,
  roleNameToSlug,
} from '@/config/permissionCatalog';

function newId() {
  return globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export const formatRoleLabel = (roleName) => {
  if (!roleName) return 'No Role';
  if (typeof roleName === 'object') {
    if (roleName.name) roleName = roleName.name;
    else return 'No Role';
  }
  if (typeof roleName !== 'string') return 'No Role';
  return roleName
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

async function syncUserRoleRecord(userId, roleName) {
  await supabase.from('user_roles').delete().eq('user_id', userId);
  const { error } = await supabase.from('user_roles').insert([
    {
      id: newId(),
      user_id: userId,
      role: roleName,
      created_at: new Date().toISOString(),
    },
  ]);
  if (error) throw error;
}

export const getAllRoles = async () => {
  try {
    const { data, error } = await supabase.from('roles').select('*').order('name', { ascending: true });
    if (error) throw error;
    return { success: true, data: data || [] };
  } catch (error) {
    console.error('[roleService] getAllRoles:', error);
    return { success: false, data: [], error: error.message };
  }
};

export const getAllUsersWithRoles = async () => {
  try {
    const [users, rolesRes, userRolesRes] = await Promise.all([
      getAllUsers(),
      getAllRoles(),
      supabase.from('user_roles').select('*'),
    ]);

    const roles = rolesRes.data || [];
    const userRoles = userRolesRes.data || [];

    const mapped = (users || []).map((user) => {
      const assignment = userRoles.find((ur) => ur.user_id === user.id);
      const roleName =
        assignment?.role ||
        findRoleNameForSlug(user.role, roles) ||
        (user.role ? formatRoleLabel(user.role) : null);

      return {
        ...user,
        full_name: user.full_name || user.name,
        role_name: roleName,
      };
    });

    return { success: true, data: mapped };
  } catch (error) {
    console.error('[roleService] getAllUsersWithRoles:', error);
    return { success: false, data: [], error: error.message };
  }
};

export const createRole = async (name, description = '') => {
  try {
    const trimmed = String(name || '').trim();
    if (!trimmed) return { success: false, error: 'Role name is required' };

    const { data, error } = await supabase
      .from('roles')
      .insert([
        {
          id: newId(),
          name: trimmed,
          description: description || '',
          is_default: false,
          created_at: new Date().toISOString(),
        },
      ])
      .select('*');

    if (error) throw error;
    return { success: true, data: data?.[0] };
  } catch (error) {
    console.error('[roleService] createRole:', error);
    return { success: false, error: error.message || 'Failed to create role' };
  }
};

export const deleteRole = async (roleId) => {
  try {
    const { data: roles, error: fetchError } = await supabase
      .from('roles')
      .select('*')
      .eq('id', roleId)
      .limit(1);
    if (fetchError) throw fetchError;

    const role = roles?.[0];
    if (!role) return { success: false, error: 'Role not found' };
    if (role.is_default) return { success: false, error: 'System roles cannot be deleted' };

    await supabase.from('role_permissions').delete().eq('role', role.name);
    const { error } = await supabase.from('roles').delete().eq('id', roleId);
    if (error) throw error;

    return { success: true };
  } catch (error) {
    console.error('[roleService] deleteRole:', error);
    return { success: false, error: error.message || 'Failed to delete role' };
  }
};

export const getRolePermissions = async (roleName) => {
  try {
    const name = typeof roleName === 'object' ? roleName.name : roleName;
    if (!name) return { success: true, data: [] };

    const { data, error } = await supabase
      .from('role_permissions')
      .select('permission')
      .eq('role', name);

    if (error) throw error;

    const rows = Array.isArray(data) ? data : [];
    const permissions = rows
      .map((row) => (typeof row === 'string' ? row : row?.permission))
      .filter(Boolean);

    return { success: true, data: permissions };
  } catch (error) {
    console.error('[roleService] getRolePermissions:', error);
    const message = typeof error === 'string' ? error : error?.message || 'Failed to load permissions';
    return { success: false, data: [], error: message };
  }
};

export const addPermissionToRole = async (roleName, permissionId) => {
  try {
    const name = typeof roleName === 'object' ? roleName.name : roleName;
    const { data: existing } = await supabase
      .from('role_permissions')
      .select('id')
      .eq('role', name)
      .eq('permission', permissionId)
      .limit(1);

    if (existing?.length) return { success: true };

    const { error } = await supabase.from('role_permissions').insert([
      {
        id: newId(),
        role: name,
        permission: permissionId,
        created_at: new Date().toISOString(),
      },
    ]);
    if (error) throw error;
    return { success: true };
  } catch (error) {
    console.error('[roleService] addPermissionToRole:', error);
    return { success: false, error: error.message };
  }
};

export const removePermissionFromRole = async (roleName, permissionId) => {
  try {
    const name = typeof roleName === 'object' ? roleName.name : roleName;
    const { error } = await supabase
      .from('role_permissions')
      .delete()
      .eq('role', name)
      .eq('permission', permissionId);
    if (error) throw error;
    return { success: true };
  } catch (error) {
    console.error('[roleService] removePermissionFromRole:', error);
    return { success: false, error: error.message };
  }
};

export const assignRoleToUser = async (userId, roleName) => {
  try {
    const slug = roleNameToSlug(roleName);
    await updateUser(userId, { role: slug });
    await syncUserRoleRecord(userId, roleName);
    return { success: true };
  } catch (error) {
    console.error('[roleService] assignRoleToUser:', error);
    return { success: false, error: error.message || 'Failed to assign role' };
  }
};

export const createUserWithRole = async (email, password, fullName, roleName) => {
  try {
    const slug = roleNameToSlug(roleName);
    const user = await createUser({
      email: email.trim(),
      password,
      full_name: fullName.trim(),
      role: slug,
    });
    if (user?.id) {
      await syncUserRoleRecord(user.id, roleName);
    }
    return { success: true, data: user };
  } catch (error) {
    console.error('[roleService] createUserWithRole:', error);
    return { success: false, error: error.message || 'Failed to create user' };
  }
};

export const getPermissionCatalog = () => ({
  success: true,
  data: getPermissionsByCategory(),
  allIds: getAllPermissionIds(),
});

// Legacy stubs kept for imports that still reference them
export const getCurrentUserRole = async () => null;
export const isSuperAdmin = async () => false;
export const isAdmin = async () => false;
export const hasPermission = async () => false;
export const getUserRoleIds = async () => [];
export const getUserPermissions = async () => ({ success: false, data: [], error: 'Use rolePermissionService' });
export const updateRole = async () => ({ success: false, error: 'Not implemented' });
