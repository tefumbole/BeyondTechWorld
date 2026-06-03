-- Alpha Bridge Announcements Module (New Vision pattern)
-- Run in Supabase SQL Editor

create table if not exists announcement_settings (
  id uuid primary key default gen_random_uuid(),
  company_name text default 'Alpha Bridge Technologies Ltd',
  default_header text default 'Alpha Bridge Technologies Ltd',
  serial_prefix text default 'ABT/ANN',
  next_serial integer default 1,
  serial_padding integer default 6,
  timezone text default 'Africa/Kigali',
  timezone_offset text default '+02:00',
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create table if not exists announcements (
  id uuid primary key default gen_random_uuid(),
  name text,
  subject text,
  header text,
  body text,
  footer text,
  category text default 'general',
  people_type text default 'customers',
  recipient_ids text,
  recipients_json jsonb default '[]'::jsonb,
  reference text,
  status text default 'draft',
  whatsapp_status text default 'draft',
  schedules_json jsonb default '[]'::jsonb,
  scheduled_at timestamptz,
  attachments_json jsonb default '[]'::jsonb,
  attachment text,
  is_sent boolean default false,
  is_active boolean default true,
  sent_count integer default 0,
  send_results_json jsonb,
  created_by uuid references auth.users(id),
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

create table if not exists announcement_templates (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  category text default 'general',
  subject text,
  header_html text,
  body_html text,
  created_at timestamptz default now()
);

create index if not exists idx_announcements_status on announcements(status);
create index if not exists idx_announcements_created on announcements(created_at desc);

-- Storage bucket (create in Dashboard if this fails)
insert into storage.buckets (id, name, public)
values ('announcement-attachments', 'announcement-attachments', true)
on conflict (id) do nothing;

alter table announcement_settings enable row level security;
alter table announcements enable row level security;
alter table announcement_templates enable row level security;

create policy "Admins manage announcement_settings"
  on announcement_settings for all
  using (auth.role() = 'authenticated');

create policy "Admins manage announcements"
  on announcements for all
  using (auth.role() = 'authenticated');

create policy "Admins manage announcement_templates"
  on announcement_templates for all
  using (auth.role() = 'authenticated');
