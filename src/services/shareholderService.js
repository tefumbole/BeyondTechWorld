import { supabase } from "@/lib/customSupabaseClient";
import { generateReferenceNumber } from "@/utils/referenceNumberGenerator";
import { getSystemSettings } from "@/services/sharePriceService";

/**
 * Shareholder Service
 * Comprehensive service for managing shareholder data
 */

export const getShareholderDisplayName = (shareholder) =>
  shareholder?.full_name?.trim() ||
  shareholder?.name?.trim() ||
  '—';

export const getShareholderWorth = (shareholder, pricePerShare = 1000) => {
  const shares = parseInt(shareholder?.shares_assigned, 10) || 0;
  const stored = parseFloat(shareholder?.investment_amount);
  if (!Number.isNaN(stored) && stored > 0) return stored;
  return shares * (parseFloat(pricePerShare) || 1000);
};

/**
 * Fetches all shareholders from database
 * @param {Object} filters - Optional filters (status, search, limit, offset)
 * @returns {Promise<Array>} Array of shareholders (never null)
 */
export const getAllShareholders = async (filters = {}) => {
  console.log('[SERVICE] getAllShareholders called');
  console.log('[SERVICE] Filters:', filters);
  
  try {
    let query = supabase
      .from('shareholders')
      .select('*', { count: 'exact' });

    if (filters.trashOnly) {
      query = query.not('deleted_at', 'is', null);
    } else if (!filters.includeDeleted) {
      query = query.is('deleted_at', null);
    }

    // Apply status filter if provided
    if (filters.status && filters.status !== 'all') {
      query = query.eq('status', filters.status);
      console.log('[SERVICE] Filtering by status:', filters.status);
    }

    // Apply search filter if provided
    if (filters.search && filters.search.trim()) {
      const searchTerm = filters.search.trim();
      query = query.or(`full_name.ilike.%${searchTerm}%,email.ilike.%${searchTerm}%,phone_number.ilike.%${searchTerm}%,full_phone_number.ilike.%${searchTerm}%,reference_number.ilike.%${searchTerm}%`);
      console.log('[SERVICE] Filtering by search:', searchTerm);
    }

    // Apply sorting
    const sortBy = filters.sortBy || 'created_at';
    const sortOrder = filters.sortOrder === 'asc';
    query = query.order(sortBy, { ascending: sortOrder });
    console.log('[SERVICE] Sorting by:', sortBy, 'ascending:', sortOrder);

    // Apply pagination
    const limit = filters.limit || 100;
    const offset = filters.offset || 0;
    query = query.range(offset, offset + limit - 1);

    console.log('[SERVICE] Applying pagination: limit', limit, 'offset', offset);

    const { data, error, count } = await query;

    if (error) {
      console.error('[SERVICE] getAllShareholders error:', error);
      throw error;
    }

    console.log('[SERVICE] Fetched', data?.length || 0, 'shareholders, total:', count);
    return Array.isArray(data) ? data : [];

  } catch (error) {
    console.error('[SERVICE] getAllShareholders catch error:', error);
    return []; // Always return array, never null
  }
};

/**
 * Fetches only approved shareholders
 * @returns {Promise<Array>} Array of approved shareholders
 */
export const getApprovedShareholders = async () => {
  console.log('[SERVICE] getApprovedShareholders called');
  
  try {
    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .eq('status', 'approved')
      .is('deleted_at', null)
      .order('approved_at', { ascending: false });

    if (error) {
      console.error('[SERVICE] getApprovedShareholders error:', error);
      throw error;
    }

    console.log('[SERVICE] Fetched', data?.length || 0, 'approved shareholders');
    return Array.isArray(data) ? data : [];

  } catch (error) {
    console.error('[SERVICE] getApprovedShareholders catch error:', error);
    return [];
  }
};

/**
 * Shareholders with a captured signature (approved or awaiting approval).
 */
export const getSignedShareholderAgreements = async () => {
  console.log('[SERVICE] getSignedShareholderAgreements called');

  try {
    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .is('deleted_at', null)
      .in('status', ['approved', 'pending_approval'])
      .order('approved_at', { ascending: false })
      .order('submitted_at', { ascending: false })
      .order('created_at', { ascending: false });

    if (error) {
      console.error('[SERVICE] getSignedShareholderAgreements error:', error);
      throw error;
    }

    const signed = (Array.isArray(data) ? data : []).filter(
      (row) => row?.signature || row?.signature_image_url || row?.agreement_signed_at
    );

    console.log('[SERVICE] Fetched', signed.length, 'signed shareholder agreements');
    return signed;
  } catch (error) {
    console.error('[SERVICE] getSignedShareholderAgreements catch error:', error);
    return [];
  }
};

/**
 * Fetches only pending approval shareholders
 * @returns {Promise<Array>} Array of pending shareholders
 */
export const getPendingShareholders = async () => {
  console.log('[SERVICE] getPendingShareholders called');
  
  try {
    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .eq('status', 'pending_approval')
      .order('submitted_at', { ascending: false });

    if (error) {
      console.error('[SERVICE] getPendingShareholders error:', error);
      throw error;
    }

    console.log('[SERVICE] Fetched', data?.length || 0, 'pending shareholders');
    return Array.isArray(data) ? data : [];

  } catch (error) {
    console.error('[SERVICE] getPendingShareholders catch error:', error);
    return [];
  }
};

/**
 * Fetches single shareholder by ID
 * @param {string} id - Shareholder UUID
 * @returns {Promise<Object|null>} Shareholder object or null
 */
export const getShareholderById = async (id) => {
  console.log('[SERVICE] getShareholderById called, ID:', id);
  
  try {
    if (!id) {
      console.warn('[SERVICE] getShareholderById: no ID provided');
      return null;
    }

    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .eq('id', id)
      .single();

    if (error) {
      console.error('[SERVICE] getShareholderById error:', error);
      throw error;
    }

    console.log('[SERVICE] Fetched shareholder:', data?.id);
    return data;

  } catch (error) {
    console.error('[SERVICE] getShareholderById catch error:', error);
    return null;
  }
};

/**
 * Fetch shareholder by reference number
 * @param {string} referenceNumber - Reference number
 * @returns {Promise<Object>} { data, error }
 */
export const getShareholderByReference = async (referenceNumber) => {
  console.log('[SERVICE] getShareholderByReference called, ref:', referenceNumber);
  
  try {
    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .eq('reference_number', referenceNumber)
      .single();

    if (error) {
      console.error('[SERVICE] getShareholderByReference error:', error);
      throw error;
    }
    
    console.log('[SERVICE] Found shareholder by reference:', data?.id);
    return { data, error: null };

  } catch (error) {
    console.error('[SERVICE] getShareholderByReference catch error:', error);
    return { data: null, error };
  }
};

/**
 * Creates new shareholder record
 * @param {Object} shareholderData - Shareholder data
 * @returns {Promise<Object>} { success, data, error }
 */
export const createShareholder = async (shareholderData) => {
  console.log('[SERVICE] createShareholder called');
  console.log('[SERVICE] Data:', { ...shareholderData, signature: '[OMITTED]' });
  
  try {
    const payload = {
      ...shareholderData,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };

    const { data, error } = await supabase
      .from('shareholders')
      .insert([payload])
      .select()
      .single();

    if (error) {
      console.error('[SERVICE] createShareholder error:', error);
      return { success: false, error };
    }

    console.log('[SERVICE] Shareholder created successfully, ID:', data.id);
    return { success: true, data };

  } catch (error) {
    console.error('[SERVICE] createShareholder catch error:', error);
    return { success: false, error };
  }
};

/**
 * Updates shareholder record
 * @param {string} id - Shareholder UUID
 * @param {Object} updates - Fields to update
 * @returns {Promise<Object>} { success, data, error }
 */
export const updateShareholder = async (id, updates) => {
  console.log('[SERVICE] updateShareholder called, ID:', id);
  console.log('[SERVICE] Updates:', updates);
  
  try {
    if (!id) {
      throw new Error('Shareholder ID is required');
    }

    const payload = {
      ...updates,
      updated_at: new Date().toISOString()
    };

    const { data, error } = await supabase
      .from('shareholders')
      .update(payload)
      .eq('id', id)
      .select()
      .single();

    if (error) {
      console.error('[SERVICE] updateShareholder error:', error);
      return { success: false, error };
    }

    console.log('[SERVICE] Shareholder updated successfully');
    return { success: true, data };

  } catch (error) {
    console.error('[SERVICE] updateShareholder catch error:', error);
    return { success: false, error };
  }
};

/**
 * Deletes shareholder record
 * @param {string} id - Shareholder UUID
 * @returns {Promise<Object>} { success, error }
 */
export const deleteShareholder = async (id) => {
  console.log('[SERVICE] deleteShareholder (soft) called, ID:', id);
  
  try {
    if (!id) {
      throw new Error('Shareholder ID is required');
    }

    const { error } = await supabase
      .from('shareholders')
      .update({ deleted_at: new Date().toISOString() })
      .eq('id', id);

    if (error) {
      console.error('[SERVICE] deleteShareholder error:', error);
      return { success: false, error };
    }

    console.log('[SERVICE] Shareholder moved to trash');
    return { success: true };

  } catch (error) {
    console.error('[SERVICE] deleteShareholder catch error:', error);
    return { success: false, error };
  }
};

export const softDeleteShareholders = async (ids = []) => {
  if (!ids.length) return { success: true, count: 0 };
  const now = new Date().toISOString();
  const { error } = await supabase
    .from('shareholders')
    .update({ deleted_at: now })
    .in('id', ids);
  if (error) return { success: false, error };
  return { success: true, count: ids.length };
};

export const restoreShareholders = async (ids = []) => {
  if (!ids.length) return { success: true, count: 0 };
  const { error } = await supabase
    .from('shareholders')
    .update({ deleted_at: null })
    .in('id', ids);
  if (error) return { success: false, error };
  return { success: true, count: ids.length };
};

export const permanentDeleteShareholders = async (ids = []) => {
  if (!ids.length) return { success: true, count: 0 };
  const { error } = await supabase
    .from('shareholders')
    .delete()
    .in('id', ids);
  if (error) return { success: false, error };
  return { success: true, count: ids.length };
};

export const updateShareholderName = async (id, fullName) => {
  if (!id || !fullName?.trim()) {
    return { success: false, error: new Error('Name is required') };
  }
  const { data, error } = await supabase
    .from('shareholders')
    .update({ full_name: fullName.trim(), name: fullName.trim() })
    .eq('id', id)
    .select()
    .single();
  if (error) return { success: false, error };
  return { success: true, data };
};

/**
 * Calculates comprehensive shareholder statistics
 * @returns {Promise<Object>} Statistics object
 */
export const getShareholderStats = async () => {
  console.log('[SERVICE] getShareholderStats called');
  
  try {
    const settings = await getSystemSettings();
    const totalCompanyShares = settings.total_shares_available || 100;
    const pricePerShare = settings.price_per_share || 1000;

    const { data: allShareholders, error } = await supabase
      .from('shareholders')
      .select('status, shares_assigned, investment_amount, payment_status, deleted_at')
      .is('deleted_at', null);

    if (error) {
      console.error('[SERVICE] getShareholderStats error:', error);
      throw error;
    }

    const shareholders = Array.isArray(allShareholders) ? allShareholders : [];
    
    const approved = shareholders.filter(s => s.status === 'approved');
    const pending = shareholders.filter(s => s.status === 'pending_approval');
    
    const totalShares = approved.reduce((sum, s) => sum + (parseInt(s.shares_assigned) || 0), 0);
    const totalInvestment = approved.reduce(
      (sum, s) => sum + getShareholderWorth(s, pricePerShare),
      0
    );
    
    const completedPayments = approved.filter(s => 
      s.payment_status === 'completed' || s.payment_status === 'paid'
    ).length;
    
    const pendingPayments = approved.filter(s => 
      s.payment_status === 'pending' || !s.payment_status
    ).length;

    const stats = {
      totalShareholders: shareholders.length,
      approvedShareholders: approved.length,
      pendingShareholders: pending.length,
      totalShares,
      totalInvestment,
      completedPayments,
      pendingPayments,
      totalCompanyShares,
      availableShares: Math.max(0, totalCompanyShares - totalShares),
      pricePerShare,
    };

    console.log('[SERVICE] Stats calculated:', stats);
    return stats;

  } catch (error) {
    console.error('[SERVICE] getShareholderStats catch error:', error);
    return {
      totalShareholders: 0,
      approvedShareholders: 0,
      pendingShareholders: 0,
      totalShares: 0,
      totalInvestment: 0,
      completedPayments: 0,
      pendingPayments: 0,
      totalCompanyShares: 100,
      availableShares: 100,
      pricePerShare: 1000,
    };
  }
};

export const getAvailableSharesForSubscription = async () => {
  const stats = await getShareholderStats();
  return stats.availableShares;
};

/**
 * PUBLIC: Saves a new shareholder registration (INSERT ONLY).
 * Does not require login - supports guest submissions.
 * FIXED: Maps frontend form fields to actual database columns
 */
export const saveShareholderRegistration = async (formData) => {
  console.log('[SERVICE] === SHAREHOLDER REGISTRATION START ===');
  console.log('[SERVICE] saveShareholderRegistration called');
  console.log('[SERVICE] Form data received:', { ...formData, signature: '[SIGNATURE DATA OMITTED]' });
  
  try {
    // Generate unique reference number
    const referenceNumber = generateReferenceNumber();
    console.log('[SERVICE] Generated reference number:', referenceNumber);
    
    // Try to get user_id if logged in, but don't fail if not
    let userId = null;
    let isGuest = true;
    
    try {
      const { data: { session } } = await supabase.auth.getSession();
      if (session?.user) {
        userId = session.user.id;
        isGuest = false;
        console.log('[SERVICE] User is logged in:', userId);
      } else {
        console.log('[SERVICE] Guest registration (no user logged in)');
      }
    } catch (e) {
      console.log('[SERVICE] Auth check skipped for public form');
    }

    // Map frontend form fields to database columns
    // CRITICAL FIX: Use actual database column names
    const payload = {
      // Personal Information (mapped to database columns)
      full_name: formData.full_name,
      email: formData.email,
      phone_number: formData.phone_number,
      country_code: formData.country_code,
      full_phone_number: formData.full_phone_number,
      
      // Optional fields
      company_name: formData.company_name || null,
      address: formData.address,
      nationality: formData.nationality || null,
      
      // Investment details (mapped correctly)
      shares_assigned: parseInt(formData.shares_count || 0),
      investment_amount: parseFloat(formData.total_investment || 0),
      
      // Signature
      signature: formData.signature,
      
      // Status fields
      status: 'pending_approval',
      payment_status: 'pending',
      
      // Guest tracking
      is_guest: isGuest,
      user_id: userId,
      
      // Reference and timestamps
      reference_number: referenceNumber,
      submitted_at: new Date().toISOString(),
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };

    console.log('[SERVICE] === PAYLOAD VALIDATION ===');
    console.log('[SERVICE] Payload keys:', Object.keys(payload));
    console.log('[SERVICE] Submitting shareholder with shares:', payload.shares_assigned);
    console.log('[SERVICE] Investment amount:', payload.investment_amount);
    console.log('[SERVICE] Is guest:', payload.is_guest);
    console.log('[SERVICE] Reference number:', payload.reference_number);
    console.log('[SERVICE] Full payload (signature omitted):', {
      ...payload,
      signature: payload.signature ? '[PRESENT]' : '[MISSING]'
    });

    console.log('[SERVICE] === DATABASE INSERT ===');
    const { data, error } = await supabase
      .from('shareholders')
      .insert([payload])
      .select()
      .single();

    if (error) {
      console.error('[SERVICE] === DATABASE ERROR ===');
      console.error('[SERVICE] Error code:', error.code);
      console.error('[SERVICE] Error message:', error.message);
      console.error('[SERVICE] Error details:', error.details);
      console.error('[SERVICE] Error hint:', error.hint);
      
      // Check for specific error codes
      if (error.code === 'PGRST204') {
        console.error('[SERVICE] PGRST204: Column not found error - Schema mismatch detected');
        console.error('[SERVICE] Problematic columns might be in:', Object.keys(payload));
      }
      
      throw error;
    }

    console.log('[SERVICE] === REGISTRATION SUCCESS ===');
    console.log('[SERVICE] Registration successful, ID:', data.id);
    console.log('[SERVICE] Reference number:', data.reference_number);
    
    return { success: true, data };

  } catch (error) {
    console.error('[SERVICE] === REGISTRATION FAILED ===');
    console.error('[SERVICE] saveShareholderRegistration catch error:', error);
    console.error('[SERVICE] Error name:', error.name);
    console.error('[SERVICE] Error message:', error.message);
    console.error('[SERVICE] Error stack:', error.stack);
    
    return { 
      success: false, 
      error: {
        message: error.message || 'Unknown error occurred',
        code: error.code || 'UNKNOWN',
        details: error.details || null
      }
    };
  }
};

/**
 * Approved shareholders with payment still pending
 */
export const getPendingPaymentShareholders = async () => {
  try {
    const { data, error } = await supabase
      .from('shareholders')
      .select('*')
      .eq('status', 'approved')
      .is('deleted_at', null)
      .order('approved_at', { ascending: false });

    if (error) throw error;

    return (data || []).filter((row) => {
      const status = (row.payment_status || '').toLowerCase();
      return !status || status === 'pending' || status === 'pay later';
    });
  } catch (error) {
    console.error('[SERVICE] getPendingPaymentShareholders error:', error);
    return [];
  }
};

export const updateShareholderPaymentStatus = async (id, paymentStatus) => {
  return updateShareholder(id, { payment_status: paymentStatus });
};

export default {
  getAllShareholders,
  getApprovedShareholders,
  getSignedShareholderAgreements,
  getPendingShareholders,
  getPendingPaymentShareholders,
  updateShareholderPaymentStatus,
  getShareholderById,
  getShareholderByReference,
  createShareholder,
  updateShareholder,
  deleteShareholder,
  getShareholderStats,
  saveShareholderRegistration
};