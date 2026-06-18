import { supabase } from '@/lib/customSupabaseClient';
import { formatBytes } from '@/utils/imageCompression';

const API_BASE = import.meta.env.VITE_API_URL || '/api';
const AUTH_STORAGE_KEY = 'alpha_supabase_auth';

function getAuthToken() {
  try {
    const raw = localStorage.getItem(AUTH_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.access_token || parsed?.currentSession?.access_token || null;
  } catch {
    return null;
  }
}

/**
 * Full database backup: dumps EVERY table via the backend and returns a Blob.
 */
export const createFullDbBackup = async () => {
  const token = getAuthToken();
  const res = await fetch(`${API_BASE}/system/backup`, {
    headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || `Backup failed (${res.status})`);
  }
  const json = await res.json();
  const tableCount = Object.keys(json.tables || {}).length;
  const recordCount = Object.values(json.tables || {}).reduce((sum, rows) => sum + (rows?.length || 0), 0);
  const blob = new Blob([JSON.stringify(json, null, 2)], { type: 'application/json' });
  return { blob, tableCount, recordCount };
};

/**
 * Full database restore from a JSON dump file. Destructive: replaces data
 * for every table contained in the file.
 */
export const restoreFullDbBackup = async (file) => {
  const text = await file.text();
  let parsed;
  try {
    parsed = JSON.parse(text);
  } catch {
    throw new Error('Selected file is not valid JSON.');
  }
  if (!parsed.tables || typeof parsed.tables !== 'object') {
    throw new Error('Invalid backup file: missing "tables".');
  }

  const token = getAuthToken();
  const res = await fetch(`${API_BASE}/system/restore`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ tables: parsed.tables }),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(json.error || `Restore failed (${res.status})`);
  }
  return json;
};

/**
 * Tables to export in dependency order (Independent -> Dependent)
 */
const BACKUP_TABLES = [
    'share_settings',
    'courses',
    'timesheet_activities',
    'members',
    'events',
    'students',
    'announcements',
    'announcement_recipients',
    'shareholders',
    'payments',
    'timesheet_entries',
    // Add other tables as needed based on schema
    'system_backups' // Include self? Maybe not to avoid recursion, but for history.
];

/**
 * Creates a JSON backup of the main database tables
 * Uploads it to storage and saves reference in system_backups table
 */
export const createFullBackup = async () => {
    try {
        const timestamp = new Date().toISOString();
        const backupData = {
            metadata: {
                timestamp,
                version: "1.0",
                type: "full_backup",
                created_by: "admin" // In a real app, this would be user ID
            },
            tables: {}
        };

        // 1. Fetch data from all tables
        await Promise.all(BACKUP_TABLES.map(async (tableName) => {
            const { data, error } = await supabase.from(tableName).select('*');
            if (error) {
                console.warn(`Warning: Could not fetch table ${tableName}`, error);
                // We continue even if one table fails, but log it
                backupData.tables[tableName] = []; 
            } else {
                backupData.tables[tableName] = data;
            }
        }));

        // 2. Create Blob
        const jsonString = JSON.stringify(backupData, null, 2);
        const blob = new Blob([jsonString], { type: 'application/json' });
        const fileName = `backup_${timestamp.replace(/[:.]/g, '-')}.json`;
        
        // 3. Upload to Storage (if bucket exists)
        // We attempt to upload to 'backups' bucket. 
        // If it fails, we still return the blob for download but mark as "local only"
        let downloadUrl = null;
        let storageSize = formatBytes(blob.size);

        try {
            const { data: uploadData, error: uploadError } = await supabase.storage
                .from('backups')
                .upload(fileName, blob, {
                    contentType: 'application/json',
                    upsert: true
                });

            if (!uploadError && uploadData) {
                 const { data: publicUrlData } = supabase.storage
                    .from('backups')
                    .getPublicUrl(fileName);
                 downloadUrl = publicUrlData.publicUrl;
            }
        } catch (storageErr) {
            console.warn("Storage upload failed (Bucket might not exist), proceeding with local download only.", storageErr);
        }

        // 4. Save Record to DB
        try {
            await supabase.from('system_backups').insert([{
                name: fileName,
                type: 'full_json',
                size: storageSize,
                status: downloadUrl ? 'stored' : 'local_only',
                download_url: downloadUrl,
                created_at: timestamp,
            }]);
        } catch (dbErr) {
            console.warn("Failed to log backup to history table", dbErr);
        }

        return blob;
    } catch (error) {
        console.error("Backup generation failed:", error);
        throw new Error("Failed to generate backup: " + error.message);
    }
};

/**
 * Restores the database from a JSON backup file
 * Warning: This uses UPSERT which will overwrite existing records with same ID
 */
export const restoreDatabaseBackup = async (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = async (e) => {
            try {
                const jsonContent = e.target.result;
                const backupData = JSON.parse(jsonContent);

                // Basic Validation
                if (!backupData.metadata || !backupData.tables) {
                    throw new Error("Invalid backup file format: Missing metadata or tables.");
                }

                const results = {
                    success: [],
                    errors: []
                };

                // Restore tables in specific order to handle Foreign Keys
                // We iterate through our defined list to ensure order
                for (const tableName of BACKUP_TABLES) {
                    if (backupData.tables[tableName] && backupData.tables[tableName].length > 0) {
                        console.log(`Restoring ${tableName} (${backupData.tables[tableName].length} records)...`);
                        
                        const { error } = await supabase
                            .from(tableName)
                            .upsert(backupData.tables[tableName], { onConflict: 'id', ignoreDuplicates: false });

                        if (error) {
                            console.error(`Error restoring ${tableName}:`, error);
                            results.errors.push(`${tableName}: ${error.message}`);
                        } else {
                            results.success.push(tableName);
                        }
                    }
                }

                resolve(results);
            } catch (error) {
                console.error("Restore processing failed:", error);
                reject(error);
            }
        };

        reader.onerror = () => reject(new Error("Failed to read file"));
        reader.readAsText(file);
    });
};

/**
 * Fetches backup history
 */
export const getBackupHistory = async () => {
    const { data, error } = await supabase
        .from('system_backups')
        .select('*')
        .order('created_at', { ascending: false });
    
    if (error) throw error;
    return data;
};

/**
 * Deletes a backup record
 */
export const deleteBackup = async (id) => {
    const { error } = await supabase
        .from('system_backups')
        .delete()
        .eq('id', id);
    
    if (error) throw error;
    return true;
};