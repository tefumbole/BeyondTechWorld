import { supabase } from '@/lib/customSupabaseClient';

const useMysql = import.meta.env.VITE_DATA_BACKEND === 'mysql';
const API_BASE = import.meta.env.VITE_API_URL || '/api';
const STORAGE_KEY = 'alpha_supabase_auth';

function getToken() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.access_token || parsed?.currentSession?.access_token || null;
  } catch {
    return null;
  }
}

async function mysqlUsersApi(path, options = {}) {
  const token = getToken();
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    const message =
      typeof json.error === 'string'
        ? json.error
        : json.error?.message || res.statusText || 'Request failed';
    throw new Error(message);
  }
  return json;
}

/**
 * User Service
 * Handles basic user CRUD operations
 */

/**
 * Get all users
 * @returns {Promise<Array>} Array of users
 */
export async function getAllUsers() {
  console.log('[USER SERVICE] Fetching all users');

  if (useMysql) {
    try {
      const { data } = await mysqlUsersApi('/users');
      console.log('[USER SERVICE] Fetched', data?.length || 0, 'users');
      return data || [];
    } catch (error) {
      console.error('[USER SERVICE] Error fetching users:', error);
      throw error;
    }
  }
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .order('created_at', { ascending: false });
    
    if (error) throw error;
    
    console.log('[USER SERVICE] Fetched', data?.length || 0, 'users');
    return data || [];
  } catch (error) {
    console.error('[USER SERVICE] Error fetching users:', error);
    throw error;
  }
}

/**
 * Get user by ID
 * @param {string} userId - User UUID
 * @returns {Promise<Object>} User object
 */
export async function getUserById(userId) {
  console.log('[USER SERVICE] Fetching user:', userId);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('id', userId)
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error fetching user:', error);
    throw error;
  }
}

/**
 * Get user by email
 * @param {string} email - User email
 * @returns {Promise<Object>} User object
 */
export async function getUserByEmail(email) {
  console.log('[USER SERVICE] Fetching user by email:', email);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('email', email)
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error fetching user:', error);
    throw error;
  }
}

/**
 * Get user by username (searches by email or full_name)
 * @param {string} username - Username to search for
 * @returns {Promise<Object>} User object
 */
export async function getUserByUsername(username) {
  console.log('[USER SERVICE] Fetching user by username:', username);
  
  try {
    // Search by email first (exact match)
    let { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('email', username)
      .maybeSingle();
    
    if (error) throw error;
    
    // If not found by email, search by full_name (case-insensitive)
    if (!data) {
      const { data: nameData, error: nameError } = await supabase
        .from('profiles')
        .select('*')
        .ilike('full_name', username)
        .maybeSingle();
      
      if (nameError) throw nameError;
      data = nameData;
    }
    
    if (!data) {
      throw new Error(`User not found: ${username}`);
    }
    
    console.log('[USER SERVICE] User found:', data.id);
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error fetching user by username:', error);
    throw error;
  }
}

/**
 * Get all users for assignment (task assignment, etc.)
 * @returns {Promise<Array>} Array of users with essential fields
 */
export async function getAllUsersForAssignment() {
  console.log('[USER SERVICE] Fetching all users for assignment');
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('id, full_name, email, phone, role')
      .eq('is_active', true)
      .order('full_name');
    
    if (error) throw error;
    
    console.log('[USER SERVICE] Fetched', data?.length || 0, 'active users for assignment');
    return data || [];
  } catch (error) {
    console.error('[USER SERVICE] Error fetching users for assignment:', error);
    throw error;
  }
}

/**
 * Create user (basic)
 * @param {Object} userData - User data
 * @returns {Promise<Object>} Created user
 */
export async function createUser(userData) {
  console.log('[USER SERVICE] Creating user');

  if (useMysql) {
    try {
      const { data, tempPassword } = await mysqlUsersApi('/users', {
        method: 'POST',
        body: JSON.stringify(userData),
      });
      if (tempPassword) {
        console.log('[USER SERVICE] Temporary password generated for', userData.email);
      }
      return data;
    } catch (error) {
      console.error('[USER SERVICE] Error creating user:', error);
      throw error;
    }
  }
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .insert(userData)
      .select()
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error creating user:', error);
    throw error;
  }
}

/**
 * Update user
 * @param {string} userId - User UUID
 * @param {Object} updates - Updates object
 * @returns {Promise<Object>} Updated user
 */
export async function updateUser(userId, updates) {
  console.log('[USER SERVICE] Updating user:', userId);

  if (useMysql) {
    try {
      const { data } = await mysqlUsersApi(`/users/${userId}`, {
        method: 'PATCH',
        body: JSON.stringify(updates),
      });
      return data;
    } catch (error) {
      console.error('[USER SERVICE] Error updating user:', error);
      throw error;
    }
  }
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .update(updates)
      .eq('id', userId)
      .select()
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error updating user:', error);
    throw error;
  }
}

/**
 * Delete user
 * @param {string} userId - User UUID
 */
export async function deleteUser(userId) {
  console.log('[USER SERVICE] Deleting user:', userId);

  if (useMysql) {
    try {
      await mysqlUsersApi(`/users/${userId}`, { method: 'DELETE' });
      console.log('[USER SERVICE] User deleted');
      return;
    } catch (error) {
      console.error('[USER SERVICE] Error deleting user:', error);
      throw error;
    }
  }
  
  try {
    const { error } = await supabase
      .from('profiles')
      .delete()
      .eq('id', userId);
    
    if (error) throw error;
    
    console.log('[USER SERVICE] User deleted');
  } catch (error) {
    console.error('[USER SERVICE] Error deleting user:', error);
    throw error;
  }
}

/**
 * Search users by name or email
 * @param {string} searchTerm - Search term
 * @returns {Promise<Array>} Array of matching users
 */
export async function searchUsers(searchTerm) {
  console.log('[USER SERVICE] Searching users:', searchTerm);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .or(`full_name.ilike.%${searchTerm}%,email.ilike.%${searchTerm}%`)
      .order('full_name');
    
    if (error) throw error;
    
    console.log('[USER SERVICE] Found', data?.length || 0, 'users');
    return data || [];
  } catch (error) {
    console.error('[USER SERVICE] Error searching users:', error);
    throw error;
  }
}

/**
 * Get users by role
 * @param {string} role - Role name
 * @returns {Promise<Array>} Array of users with the specified role
 */
export async function getUsersByRole(role) {
  console.log('[USER SERVICE] Fetching users by role:', role);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('role', role)
      .order('full_name');
    
    if (error) throw error;
    
    console.log('[USER SERVICE] Found', data?.length || 0, 'users with role:', role);
    return data || [];
  } catch (error) {
    console.error('[USER SERVICE] Error fetching users by role:', error);
    throw error;
  }
}

/**
 * Activate user
 * @param {string} userId - User UUID
 * @returns {Promise<Object>} Updated user
 */
export async function activateUser(userId) {
  console.log('[USER SERVICE] Activating user:', userId);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .update({ is_active: true })
      .eq('id', userId)
      .select()
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error activating user:', error);
    throw error;
  }
}

/**
 * Deactivate user
 * @param {string} userId - User UUID
 * @returns {Promise<Object>} Updated user
 */
export async function deactivateUser(userId) {
  console.log('[USER SERVICE] Deactivating user:', userId);
  
  try {
    const { data, error } = await supabase
      .from('profiles')
      .update({ is_active: false })
      .eq('id', userId)
      .select()
      .single();
    
    if (error) throw error;
    
    return data;
  } catch (error) {
    console.error('[USER SERVICE] Error deactivating user:', error);
    throw error;
  }
}

export default {
  getAllUsers,
  getUserById,
  getUserByEmail,
  getUserByUsername,
  getAllUsersForAssignment,
  createUser,
  updateUser,
  deleteUser,
  searchUsers,
  getUsersByRole,
  activateUser,
  deactivateUser
};