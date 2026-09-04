-- Supabase PostgreSQL. Apply once to a NEW, dedicated project. No customer seeds.
begin;
create schema if not exists portal_private;
revoke all on schema portal_private from public, anon;
grant usage on schema portal_private to authenticated, supabase_auth_admin;

create table public.portal_staff (
  id uuid primary key default gen_random_uuid(),
  email text not null unique check (email = lower(btrim(email)) and length(email) between 3 and 254),
  user_id uuid unique references auth.users(id) on delete set null,
  role text not null check (role in ('admin','staff')),
  active boolean not null default true,
  created_at timestamptz not null default now()
);
create table public.portal_projects (
  id uuid primary key default gen_random_uuid(),
  code text not null unique check (code ~ '^[A-Z0-9-]{3,32}$'),
  title text not null check (length(btrim(title)) between 1 and 160),
  client_name text not null check (length(btrim(client_name)) between 1 and 160),
  summary text not null default '' check (length(summary) <= 4000),
  phase text not null default 'discovery' check (phase in ('discovery','design','development','testing','delivery')),
  health text not null default 'on_track' check (health in ('on_track','at_risk','blocked')),
  due_on date,
  next_action text not null default '' check (length(next_action) <= 1000),
  preview_url text check (preview_url is null or (preview_url ~ '^https://' and length(preview_url) <= 2048)),
  archived boolean not null default false,
  created_by uuid default auth.uid() references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create table public.portal_members (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.portal_projects(id) on delete cascade,
  email text not null check (email = lower(btrim(email)) and length(email) between 3 and 254),
  user_id uuid references auth.users(id) on delete set null,
  role text not null default 'client' check (role in ('client','staff')),
  revoked boolean not null default false,
  created_at timestamptz not null default now(),
  unique(project_id,email)
);
create index portal_members_user_project on public.portal_members(user_id,project_id) where not revoked;

create table public.portal_milestones (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.portal_projects(id) on delete cascade,
  title text not null check (length(btrim(title)) between 1 and 160),
  description text not null default '' check (length(description) <= 4000),
  due_on date,
  position integer not null default 0 check (position between 0 and 9999),
  status text not null default 'planned' check (status in ('planned','in_progress','submitted','approved')),
  approved_by uuid references auth.users(id) on delete set null,
  approved_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check ((status = 'approved' and approved_at is not null) or (status <> 'approved' and approved_at is null and approved_by is null))
);
create index portal_milestones_project on public.portal_milestones(project_id,position);
create table public.portal_updates (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.portal_projects(id) on delete cascade,
  title text not null check (length(btrim(title)) between 1 and 160),
  body text not null check (length(btrim(body)) between 1 and 6000),
  kind text not null default 'progress' check (kind in ('progress','weekly','action')),
  created_by uuid default auth.uid() references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index portal_updates_project_time on public.portal_updates(project_id,created_at desc);
create table public.portal_requests (
  id uuid primary key default gen_random_uuid(),
  project_id uuid not null references public.portal_projects(id) on delete cascade,
  kind text not null check (kind in ('change','support')),
  title text not null check (length(btrim(title)) between 1 and 160),
  body text not null check (length(btrim(body)) between 1 and 6000),
  priority text not null default 'normal' check (priority in ('normal','high')),
  status text not null default 'open' check (status in ('open','reviewing','in_progress','resolved','closed')),
  response text not null default '' check (length(response) <= 6000),
  created_by uuid not null default auth.uid() references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index portal_requests_project_time on public.portal_requests(project_id,created_at desc);
create table public.portal_audit_events (
  id bigint generated always as identity primary key,
  project_id uuid,
  entity_table text not null,
  entity_id text not null,
  action text not null,
  actor_id uuid,
  happened_at timestamptz not null default now()
);

-- Security-definer helpers are NOT exposed through the public REST schema.
create function portal_private.verified_email() returns text language sql stable security definer set search_path = '' as $$
  select lower(u.email) from auth.users u where u.id = auth.uid()
    and u.email_confirmed_at is not null and not coalesce(u.is_anonymous,false)
$$;
create function portal_private.staff_role() returns text language sql stable security definer set search_path = '' as $$
  select s.role from public.portal_staff s where s.user_id = auth.uid() and s.active
    and s.email = portal_private.verified_email()
$$;
create function portal_private.can_manage(pid uuid) returns boolean language sql stable security definer set search_path = '' as $$
  select coalesce(portal_private.staff_role() = 'admin',false) or
    (coalesce(portal_private.staff_role() = 'staff',false) and exists (
      select 1 from public.portal_members m where m.project_id = pid and m.user_id = auth.uid()
        and m.role = 'staff' and not m.revoked and m.email = portal_private.verified_email()))
$$;
create function portal_private.is_client(pid uuid) returns boolean language sql stable security definer set search_path = '' as $$
  select exists (select 1 from public.portal_members m join public.portal_projects p on p.id=m.project_id
    where m.project_id=pid and m.user_id=auth.uid() and m.role='client' and not m.revoked
      and not p.archived and m.email=portal_private.verified_email())
$$;
create function portal_private.can_read(pid uuid) returns boolean language sql stable security definer set search_path = '' as $$
  select portal_private.can_manage(pid) or portal_private.is_client(pid)
$$;
revoke all on all functions in schema portal_private from public, anon;
grant execute on function portal_private.verified_email(), portal_private.staff_role(), portal_private.can_manage(uuid), portal_private.is_client(uuid), portal_private.can_read(uuid) to authenticated;

-- Bind invitations to a server-verified auth ID, never to a client-supplied user ID.
create function portal_private.bind_invitation() returns trigger language plpgsql security definer set search_path = '' as $$
begin
  new.email := lower(btrim(new.email));
  select u.id into new.user_id from auth.users u where lower(u.email)=new.email
    and u.email_confirmed_at is not null and not coalesce(u.is_anonymous,false);
  return new;
end $$;
create trigger portal_bind_staff before insert or update of email on public.portal_staff for each row execute function portal_private.bind_invitation();
create trigger portal_bind_member before insert or update of email on public.portal_members for each row execute function portal_private.bind_invitation();
create function portal_private.bind_verified_user() returns trigger language plpgsql security definer set search_path = '' as $$
begin
  if new.email_confirmed_at is not null and not coalesce(new.is_anonymous,false) then
    update public.portal_staff set user_id=new.id where email=lower(new.email) and (user_id is null or user_id=new.id);
    update public.portal_members set user_id=new.id where email=lower(new.email) and (user_id is null or user_id=new.id);
  end if;
  return new;
end $$;
create trigger portal_bind_verified after insert or update of email,email_confirmed_at on auth.users for each row execute function portal_private.bind_verified_user();

-- Enable as Supabase Auth > Hooks > Before User Created. Grant ONLY to Auth.
create function public.portal_before_user_created(event jsonb) returns jsonb language plpgsql security definer set search_path = '' as $$
declare requested_email text := lower(btrim(event->'user'->>'email'));
begin
  if coalesce(event->'user'->'app_metadata'->>'provider','email') <> 'email' then
    return jsonb_build_object('error',jsonb_build_object('http_code',403,'message','Registration is not available.'));
  end if;
  if exists(select 1 from public.portal_staff where email=requested_email and active)
    or exists(select 1 from public.portal_members m join public.portal_projects p on p.id=m.project_id
      where m.email=requested_email and not m.revoked and not p.archived) then return '{}'::jsonb; end if;
  return jsonb_build_object('error',jsonb_build_object('http_code',403,'message','Registration is not available.'));
end $$;
revoke all on function public.portal_before_user_created(jsonb) from public, anon, authenticated;
grant execute on function public.portal_before_user_created(jsonb) to supabase_auth_admin;

create function public.portal_session() returns jsonb language sql stable security definer set search_path = '' as $$
  select jsonb_build_object('role',coalesce(portal_private.staff_role(),'client'),'verified',portal_private.verified_email() is not null)
$$;
revoke all on function public.portal_session() from public, anon;
grant execute on function public.portal_session() to authenticated;

create function portal_private.touch_row() returns trigger language plpgsql set search_path = '' as $$
begin new.updated_at := clock_timestamp(); return new; end $$;
create trigger portal_touch_project before update on public.portal_projects for each row execute function portal_private.touch_row();
create trigger portal_touch_milestone before update on public.portal_milestones for each row execute function portal_private.touch_row();
create trigger portal_touch_update before update on public.portal_updates for each row execute function portal_private.touch_row();
create trigger portal_touch_request before update on public.portal_requests for each row execute function portal_private.touch_row();
create function portal_private.guard_archive() returns trigger language plpgsql set search_path = '' as $$
begin
  if new.archived is distinct from old.archived and coalesce(portal_private.staff_role(),'') <> 'admin' then
    raise exception 'Only admins may archive projects' using errcode='42501';
  end if; return new;
end $$;
create trigger portal_guard_archive before update on public.portal_projects for each row execute function portal_private.guard_archive();

alter table public.portal_staff enable row level security;
alter table public.portal_projects enable row level security;
alter table public.portal_members enable row level security;
alter table public.portal_milestones enable row level security;
alter table public.portal_updates enable row level security;
alter table public.portal_requests enable row level security;
alter table public.portal_audit_events enable row level security;

revoke all on public.portal_staff,public.portal_projects,public.portal_members,public.portal_milestones,public.portal_updates,public.portal_requests,public.portal_audit_events from anon,authenticated;
grant select on public.portal_staff,public.portal_projects,public.portal_members,public.portal_milestones,public.portal_updates,public.portal_requests,public.portal_audit_events to authenticated;
-- Staff administration is SQL/dashboard-only initially; no browser can promote itself.
create policy staff_read on public.portal_staff for select to authenticated using (portal_private.staff_role()='admin' or (user_id=auth.uid() and email=portal_private.verified_email()));

grant insert(code,title,client_name,summary,phase,health,due_on,next_action,preview_url) on public.portal_projects to authenticated;
grant update(title,client_name,summary,phase,health,due_on,next_action,preview_url,archived) on public.portal_projects to authenticated;
create policy project_read on public.portal_projects for select to authenticated using (portal_private.can_read(id));
create policy project_insert on public.portal_projects for insert to authenticated with check (portal_private.staff_role()='admin' and not archived);
create policy project_update on public.portal_projects for update to authenticated using (portal_private.can_manage(id)) with check (portal_private.can_manage(id));

grant insert(project_id,email,role) on public.portal_members to authenticated;
grant update(revoked,role) on public.portal_members to authenticated;
grant delete on public.portal_members to authenticated;
create policy member_read on public.portal_members for select to authenticated using (portal_private.staff_role()='admin' or (user_id=auth.uid() and email=portal_private.verified_email() and not revoked));
create policy member_insert on public.portal_members for insert to authenticated with check (portal_private.staff_role()='admin');
create policy member_update on public.portal_members for update to authenticated using (portal_private.staff_role()='admin') with check (portal_private.staff_role()='admin');
create policy member_delete on public.portal_members for delete to authenticated using (portal_private.staff_role()='admin');

grant insert(project_id,title,description,due_on,position,status) on public.portal_milestones to authenticated;
grant update(title,description,due_on,position,status) on public.portal_milestones to authenticated;
grant delete on public.portal_milestones to authenticated;
create policy milestone_read on public.portal_milestones for select to authenticated using (portal_private.can_read(project_id));
create policy milestone_insert on public.portal_milestones for insert to authenticated with check (portal_private.can_manage(project_id) and status<>'approved');
create policy milestone_update on public.portal_milestones for update to authenticated using (portal_private.can_manage(project_id) and status<>'approved') with check (portal_private.can_manage(project_id) and status<>'approved');
create policy milestone_delete on public.portal_milestones for delete to authenticated using (portal_private.can_manage(project_id) and status<>'approved');

grant insert(project_id,title,body,kind) on public.portal_updates to authenticated;
grant update(title,body,kind) on public.portal_updates to authenticated;
grant delete on public.portal_updates to authenticated;
create policy update_read on public.portal_updates for select to authenticated using (portal_private.can_read(project_id));
create policy update_insert on public.portal_updates for insert to authenticated with check (portal_private.can_manage(project_id));
create policy update_edit on public.portal_updates for update to authenticated using (portal_private.can_manage(project_id)) with check (portal_private.can_manage(project_id));
create policy update_delete on public.portal_updates for delete to authenticated using (portal_private.can_manage(project_id));

grant insert(project_id,kind,title,body,priority) on public.portal_requests to authenticated;
grant update(status,response) on public.portal_requests to authenticated;
create policy request_read on public.portal_requests for select to authenticated using (portal_private.can_read(project_id));
create policy request_insert on public.portal_requests for insert to authenticated with check (portal_private.is_client(project_id) and created_by=auth.uid() and status='open' and response='');
create policy request_update on public.portal_requests for update to authenticated using (portal_private.can_manage(project_id)) with check (portal_private.can_manage(project_id));
create policy audit_read on public.portal_audit_events for select to authenticated using (portal_private.staff_role()='admin');

-- This is the only client mutation of a milestone. Identity, membership, archive
-- state and submitted/version state are checked inside the database transaction.
create function public.portal_approve_milestone(mid uuid, expected_updated_at timestamptz) returns public.portal_milestones language plpgsql security definer set search_path = '' as $$
declare item public.portal_milestones;
begin
  select * into item from public.portal_milestones where id=mid for update;
  if not found or not portal_private.is_client(item.project_id) then
    raise exception 'Not available' using errcode='42501';
  end if;
  -- Hold the membership and project locks until commit to serialize revocation/archive.
  perform 1 from public.portal_projects where id=item.project_id and not archived for share;
  perform 1 from public.portal_members where project_id=item.project_id and user_id=auth.uid()
    and role='client' and not revoked and email=portal_private.verified_email() for share;
  if not found or not portal_private.is_client(item.project_id) then raise exception 'Not available' using errcode='42501'; end if;
  if item.status<>'submitted' or item.updated_at is distinct from expected_updated_at then
    raise exception 'Milestone changed; reload before approving' using errcode='40001';
  end if;
  update public.portal_milestones set status='approved',approved_by=auth.uid(),approved_at=now() where id=mid returning * into item;
  return item;
end $$;
revoke all on function public.portal_approve_milestone(uuid,timestamptz) from public,anon;
grant execute on function public.portal_approve_milestone(uuid,timestamptz) to authenticated;

create function portal_private.audit_change() returns trigger language plpgsql security definer set search_path = '' as $$
declare row_data jsonb;
begin
  if tg_op='DELETE' then row_data:=to_jsonb(old); else row_data:=to_jsonb(new); end if;
  insert into public.portal_audit_events(project_id,entity_table,entity_id,action,actor_id)
    values(case when tg_table_name='portal_projects' then (row_data->>'id')::uuid else (row_data->>'project_id')::uuid end,
      tg_table_name,row_data->>'id',tg_op,auth.uid());
  return null;
end $$;
create trigger portal_audit_project after insert or update or delete on public.portal_projects for each row execute function portal_private.audit_change();
create trigger portal_audit_member after insert or update or delete on public.portal_members for each row execute function portal_private.audit_change();
create trigger portal_audit_milestone after insert or update or delete on public.portal_milestones for each row execute function portal_private.audit_change();
create trigger portal_audit_update after insert or update or delete on public.portal_updates for each row execute function portal_private.audit_change();
create trigger portal_audit_request after insert or update or delete on public.portal_requests for each row execute function portal_private.audit_change();
revoke all on function portal_private.bind_invitation(),portal_private.bind_verified_user(),portal_private.touch_row(),portal_private.guard_archive(),portal_private.audit_change() from public,anon,authenticated;
commit;
