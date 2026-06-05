import { supabase } from '@/lib/supabaseClient';

const LOG_TABLE = 'whatsapp_message_logs';

/**
 * Logs a WhatsApp message attempt to the database.
 * @param {Object} logData - { recipient_phone, message_type, status, error_message, related_registration_id }
 */
export const logWhatsAppMessage = async (logData) => {
    try {
        const phone = logData.recipient_phone || logData.phone_number;
        const { error } = await supabase
            .from(LOG_TABLE)
            .insert([{
                recipient_phone: phone,
                phone_number: phone,
                message_type: logData.message_type,
                status: logData.status,
                error_message: logData.error_message || null,
                related_registration_id: logData.related_registration_id || null,
                sent_at: new Date().toISOString(),
                retry_count: 0
            }]);

        if (error) {
            console.error("Failed to log WhatsApp message:", error);
        }
    } catch (err) {
        console.error("Error in logWhatsAppMessage:", err);
    }
};

/**
 * Fetches WhatsApp message logs for admin panel.
 */
export const getWhatsAppLogs = async () => {
    try {
        const { data, error } = await supabase
            .from(LOG_TABLE)
            .select('*')
            .order('sent_at', { ascending: false });

        if (error) throw error;
        return {
            data: (data || []).map((row) => ({
                ...row,
                recipient_phone: row.recipient_phone || row.phone_number,
            })),
            error: null,
        };
    } catch (error) {
        console.error("Error fetching WhatsApp logs:", error);
        return { data: [], error };
    }
};

/**
 * Updates the retry count for a log entry.
 */
export const updateRetryCount = async (logId, count) => {
    try {
        await supabase
            .from(LOG_TABLE)
            .update({ retry_count: count, sent_at: new Date().toISOString() })
            .eq('id', logId);
    } catch (err) {
        console.error("Error updating retry count:", err);
    }
};
