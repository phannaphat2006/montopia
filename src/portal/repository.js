import {createClient} from '@supabase/supabase-js';
import {normalizeEmail} from './domain.js';
function result({data,error}) { if(error) throw error; return data; }
export function createRepository(config) {
  if(!config?.enabled || !config.supabaseUrl || !config.publishableKey) return null;
  const db=createClient(config.supabaseUrl,config.publishableKey,{auth:{storage:window.sessionStorage,persistSession:true,autoRefreshToken:true,detectSessionInUrl:false}});
  return {
    async session(){ const {data,error}=await db.auth.getUser(); if(error||!data.user) return null; const access=result(await db.rpc('portal_session')); return access.verified ? {...access,email:data.user.email,id:data.user.id} : null; },
    async sendOtp(email){ result(await db.auth.signInWithOtp({email:normalizeEmail(email),options:{shouldCreateUser:true}})); },
    async verify(email,token){ result(await db.auth.verifyOtp({email:normalizeEmail(email),token,type:'email'})); },
    async signOut(){ result(await db.auth.signOut({scope:'local'})); },
    onSignOut(callback){ return db.auth.onAuthStateChange(event=>{if(event==='SIGNED_OUT')callback();}).data.subscription; },
    async projects(){ return result(await db.from('portal_projects').select('*').order('archived').order('updated_at',{ascending:false})); },
    async detail(id,admin){ const tables=['milestones','updates','requests',...(admin?['members']:[])]; const rows=await Promise.all(tables.map(async table=>[table,result(await db.from('portal_'+table).select('*').eq('project_id',id).order(table==='milestones'?'position':'created_at',{ascending:table==='milestones'}))])); return {members:[],...Object.fromEntries(rows)}; },
    async save(table,values,record){
      let query=record ? db.from('portal_'+table).update(values).eq('id',record.id) : db.from('portal_'+table).insert(values);
      if(record?.updated_at)query=query.eq('updated_at',record.updated_at);
      return result(await query.select().single());
    },
    async remove(table,record){let query=db.from('portal_'+table).delete().eq('id',record.id);if(record.updated_at)query=query.eq('updated_at',record.updated_at);return result(await query.select('id').single());},
    async approve(record){return result(await db.rpc('portal_approve_milestone',{mid:record.id,expected_updated_at:record.updated_at}));}
  };
}
