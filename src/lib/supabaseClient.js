/**
 * Single Supabase / MySQL client entry point.
 * Re-exports from customSupabaseClient so all imports share the same backend.
 */
export { default, supabase, customSupabaseClient } from './customSupabaseClient.js';
