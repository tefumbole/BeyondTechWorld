import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RefreshCw } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import { sendWhatsAppMessage } from '@/services/wasenderapiService';
import { updateRetryCount } from '@/services/whatsappLogService';
import { supabase } from '@/lib/supabaseClient';

/**
 * Utility Component to Retry WhatsApp Messages
 * @param {Object} logEntry - The log object { id, recipient_phone, message_type, retry_count, ... }
 * @param {Function} onRetrySuccess - Callback to refresh the list
 */
const WhatsAppRetryHandler = ({ logEntry, onRetrySuccess }) => {
  const [loading, setLoading] = useState(false);
  const { toast } = useToast();

  const handleRetry = async () => {
    if (logEntry.retry_count >= 3) {
      toast({
        title: "Max Retries Reached",
        description: "This message has been retried too many times.",
        variant: "destructive"
      });
      return;
    }

    setLoading(true);

    try {
      // Re-construct a generic message based on type since we don't store the exact message text in the log
      // In a real app, we should store the message body in the log table
      let message = "";
      if (logEntry.message_type === 'master_class') {
         message = `Hi, this is a retry for your Master Class confirmation. Please contact support if you have questions.`;
      } else if (logEntry.message_type === 'shareholder') {
         message = `Hi, this is a retry for your Shareholder Investment confirmation. Please contact support.`;
      } else {
         message = `Hi, this is a retry for your Event Registration confirmation.`;
      }

      const res = await sendWhatsAppMessage(logEntry.recipient_phone, message);

      if (res.success) {
        // Update log status
        await supabase
           .from('whatsapp_message_logs')
           .update({
               status: 'success', 
               error_message: null, 
               retry_count: (logEntry.retry_count || 0) + 1 
           })
           .eq('id', logEntry.id);

        toast({
            title: "Success",
            description: "Message resent successfully!",
            variant: "default",
            className: "bg-green-600 text-white"
        });
        
        if (onRetrySuccess) onRetrySuccess();
      } else {
        // Update retry count only
        await updateRetryCount(logEntry.id, (logEntry.retry_count || 0) + 1);
        toast({
            title: "Retry Failed",
            description: res.error || "Could not send message.",
            variant: "destructive"
        });
      }
    } catch (error) {
      console.error("Retry error:", error);
      toast({
          title: "Error",
          description: "An unexpected error occurred.",
          variant: "destructive"
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Button 
      variant="outline" 
      size="sm" 
      onClick={handleRetry} 
      disabled={loading || logEntry.status === 'success' || logEntry.retry_count >= 3}
      className="flex items-center gap-1"
    >
      <RefreshCw className={`h-3 w-3 ${loading ? 'animate-spin' : ''}`} />
      {loading ? 'Sending...' : 'Retry'}
    </Button>
  );
};

export default WhatsAppRetryHandler;