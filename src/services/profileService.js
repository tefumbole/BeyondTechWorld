import { supabase } from '@/lib/customSupabaseClient';

/**
 * Creates a new profile for a user
 * @param {string} userId - UUID from auth.users
 * @param {string} email 
 * @param {string} fullName 
 * @param {string} phone 
 * @param {string} role 
 */
export const createProfile = async (userId, email, fullName, phone, role) => {
  try {
    const { data, error } = await supabase
      .from('profiles')
      .insert([
        {
          id: userId,
          email,
          full_name: fullName,
          phone,
          role: role || 'student',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          status: 'active'
        }
      ])
      .select()
      .single();

    if (error) throw error;
    return data;
  } catch (error) {
    console.error("Error creating profile:", error);
    throw error;
  }
};

/**
 * Fetches a single profile by User ID
 * @param {string} userId 
 */
export const getProfile = async (userId) => {
  try {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('id', userId)
      .single();

    if (error) {
      // Handle "Row not found" or "JSON object requested, multiple (or no) rows returned"
      if (error.code === 'PGRST116') {
        return null;
      }
      throw error;
    }
    
    return data;
  } catch (error) {
    console.error("Error fetching profile:", error);
    // Return null on error to prevent app crashes, handle null in UI
    return null;
  }
};

/**
 * Updates an existing profile
 * @param {string} userId 
 * @param {object} updates 
 */
export const updateProfile = async (userId, updates) => {
  try {
    const { data, error } = await supabase
      .from('profiles')
      .update({
        ...updates,
        updated_at: new Date().toISOString(),
      })
      .eq('id', userId)
      .select()
      .single();

    if (error) throw error;

    if (import.meta.env.VITE_DATA_BACKEND === 'mysql') {
      const userUpdates = {};
      if (updates.full_name !== undefined) userUpdates.name = updates.full_name;
      if (updates.phone !== undefined) userUpdates.phone = updates.phone;
      if (Object.keys(userUpdates).length) {
        const { error: userError } = await supabase
          .from('users')
          .update(userUpdates)
          .eq('id', userId);
        if (userError) throw userError;
      }
    }

    return data;
  } catch (error) {
    console.error('Error updating profile:', error);
    throw error;
  }
};