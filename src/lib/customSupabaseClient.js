import { createClient } from '@supabase/supabase-js';
import mysqlDataClient from './mysqlDataClient.js';

const useMysql = import.meta.env.VITE_DATA_BACKEND === 'mysql';

let client;

if (useMysql) {
  client = mysqlDataClient;
} else {
  const supabaseUrl =
    import.meta.env.VITE_SUPABASE_URL || 'https://xnfurysmtmxkfsdjghow.supabase.co';
  const supabaseAnonKey =
    import.meta.env.VITE_SUPABASE_ANON_KEY ||
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhuZnVyeXNtdG14a2ZzZGpnaG93Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA1NTMyMDEsImV4cCI6MjA4NjEyOTIwMX0.VBmIxYtdIyL68g3YZ-InQtw9hztEFm11yFtC_2vVbRk';

  client = createClient(supabaseUrl, supabaseAnonKey);
}

export default client;
export { client as customSupabaseClient, client as supabase };
