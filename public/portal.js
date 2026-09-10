document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('workspace');
    const dialog = document.getElementById('workspace-dialog');
    const notice = document.getElementById('notice');
    let csrf = '';
    let user = null;
    let active = 'overview';

    const el = (tag, props = {}, ...children) => {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(props)) {
            if (key === 'class') node.className = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value);
            else if (key === 'text') node.textContent = value;
            else if (value !== undefined && value !== null) node.setAttribute(key, value);
        }
        for (const child of children.flat(Infinity)) {
            if (child !== null && child !== undefined) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
        }
        return node;
    };

    const button = (text, onClick, classes = '') => el('button', { type: 'button', class: classes, onClick }, text);
    const badge = (text, status = '') => el('span', { class: `badge ${status}` }, text);
    const arrowIcon = () => el('span', { class: 'icon-arrow', 'aria-hidden': 'true' });
    const publishedBadge = value => badge(value ? 'เผยแพร่' : 'ซ่อน', value ? 'approved' : 'draft');
    const yesNoOptions = [['1', 'เผยแพร่'], ['0', 'ซ่อนจากหน้าเว็บไซต์']];

    function flash(text, isError = false) {
        notice.textContent = text;
        notice.className = isError ? 'error' : '';
        notice.hidden = false;
        setTimeout(() => { notice.hidden = true; }, 5000);
    }

    async function token() {
        if (csrf) return csrf;
        const response = await fetch('/api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        csrf = (await response.json()).token;
        return csrf;
    }

    async function api(url, options = {}) {
        const method = options.method || 'GET';
        const headers = {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': await token() } : {}),
            ...options.headers,
        };
        const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });
        if (response.status === 204) return null;
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(body.errors || {})[0]?.[0] || body.message || 'ดำเนินการไม่สำเร็จ');
        return body;
    }

    function field(name, label, type = 'text', options = null, required = true) {
        let input;
        if (options) {
            input = el('select', { name, required: required ? '' : null }, options.map(([value, text]) => el('option', { value }, text)));
        } else if (type === 'textarea') {
            input = el('textarea', { name, required: required ? '' : null, maxlength: 30000, rows: 5 });
        } else {
            input = el('input', { name, type, required: required ? '' : null, maxlength: ['number', 'date', 'datetime-local'].includes(type) ? null : 255 });
        }
        return el('label', { class: 'field' }, label, input);
    }

    function fillForm(values) {
        if (!values) return;
        dialog.querySelectorAll('[name]').forEach(input => {
            let value = values[input.name];
            if (value === null || value === undefined) value = '';
            if (input.type === 'date') value = String(value).slice(0, 10);
            if (input.type === 'datetime-local') value = String(value).replace(' ', 'T').slice(0, 16);
            if (typeof value === 'boolean') value = value ? '1' : '0';
            input.value = String(value);
        });
    }

    function openForm(title, fields, submit, values = null) {
        const form = el('form');
        const error = el('p', { class: 'form-error', role: 'alert' });
        for (const item of fields) form.append(field(...item));
        const save = el('button', { type: 'submit', class: 'primary' }, 'บันทึกข้อมูล');
        form.append(error, el('div', { class: 'actions' }, button('ยกเลิก', () => dialog.close()), save));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            save.disabled = true;
            error.textContent = '';
            const data = Object.fromEntries(new FormData(form));
            try {
                await submit(data);
                dialog.close();
                flash('บันทึกข้อมูลเรียบร้อย');
                await renderDashboard();
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                save.disabled = false;
            }
        });
        dialog.replaceChildren(el('header', {}, el('h2', {}, title), button('ปิด ×', () => dialog.close(), 'small')), form);
        fillForm(values);
        dialog.showModal();
    }

    async function removeRecord(url, label) {
        if (!confirm(`ยืนยันการลบ “${label}” ออกจากระบบ?`)) return;
        try {
            await api(url, { method: 'DELETE' });
            flash('ลบข้อมูลเรียบร้อย');
            await renderDashboard();
        } catch (problem) {
            flash(problem.message, true);
        }
    }

    function login() {
        const form = el('form', {}, field('email', 'อีเมล', 'email'), field('password', 'รหัสผ่าน', 'password'));
        const error = el('p', { class: 'form-error', role: 'alert' });
        const submit = el('button', { class: 'primary has-next-icon', type: 'submit' }, 'เข้าสู่ระบบ');
        form.append(submit, error);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            submit.disabled = true;
            try {
                user = (await api('/api/auth/login', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) })).user;
                active = user.role === 'client' ? 'projects' : 'overview';
                renderDashboard();
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                submit.disabled = false;
            }
        });
        root.replaceChildren(el('div', { class: 'login-layout' },
            el('section', { class: 'login-intro' }, el('p', { class: 'eyebrow' }, 'MONSTOPIA INFORMATION SYSTEM'), el('h1', {}, 'จัดการเนื้อหาและติดตามโครงการ', el('br'), 'จากข้อมูลชุดเดียวกัน'), el('p', { class: 'muted' }, 'ระบบหลังบ้านสำหรับผู้ดูแลและทีมงาน พร้อม Client Workspace สำหรับลูกค้าที่ได้รับบัญชี')),
            el('section', { class: 'panel login-card' }, el('p', { class: 'eyebrow' }, 'SECURE ACCESS'), el('h2', {}, 'เข้าสู่ระบบ'), el('p', { class: 'muted' }, 'ใช้บัญชีที่ผู้ดูแลระบบสร้างให้ ไม่มีระบบสมัครสมาชิกสาธารณะ'), form, el('p', { class: 'caption' }, 'ระบบตรวจสิทธิ์ทุกคำขอจากฝั่ง Laravel'))
        ));
    }

    async function logout() {
        try { await api('/api/auth/logout', { method: 'POST' }); } finally { user = null; csrf = ''; login(); }
    }

    function shell(content) {
        return el('div', { class: 'workspace-shell' },
            el('aside', { class: 'sidebar' }, el('p', { class: 'eyebrow' }, 'ACCOUNT'), el('strong', {}, user.name), el('p', { class: 'caption' }, user.email, el('br'), user.role.toUpperCase()), el('div', { class: 'account' }, button('ออกจากระบบ', logout, 'small'))),
            content
        );
    }

    function goTo(key) {
        active = key;
        renderDashboard();
    }

    function row(title, body, status, actions = []) {
        return el('article', { class: 'panel feed-item' }, el('div', { class: 'page-meta' }, status), el('h3', {}, title), el('p', {}, body), el('div', { class: 'actions' }, actions));
    }

    function section(title, addLabel, onAdd, items, emptyText) {
        return el('section', {},
            el('div', { class: 'section-heading' }, el('h2', {}, title), addLabel ? button(`+ ${addLabel}`, onAdd, 'primary small') : null),
            items.length ? el('div', { class: 'request-grid' }, items) : el('div', { class: 'panel empty' }, emptyText)
        );
    }

    async function renderDashboard() {
        root.replaceChildren(el('p', { class: 'loading' }, 'กำลังโหลดข้อมูลล่าสุด…'));
        try {
            if (user.role === 'client') {
                const projects = (await api('/api/client/projects')).data;
                root.replaceChildren(shell(el('section', { class: 'main-content' },
                    el('div', { class: 'page-heading' }, el('div', {}, el('p', { class: 'eyebrow' }, 'CLIENT WORKSPACE'), el('h1', {}, 'ความคืบหน้าโครงการของคุณ'), el('p', { class: 'muted' }, `ข้อมูลโครงการของบัญชี ${user.email}`))),
                    projects.length ? el('div', { class: 'request-grid' }, projects.map(project => row(project.project_name, `เริ่ม ${date(project.start_date)} · ${project.milestones.length} งวดงาน`, badge(project.status, project.status), [button('ดู Milestone', () => showMilestones(project), 'small')]))) : el('div', { class: 'panel empty' }, 'ยังไม่มีโครงการที่ผูกกับบัญชีนี้')
                )));
                return;
            }

            const tabs = [
                ['overview', 'ภาพรวม'], ['projects', 'โครงการ'], ['inquiries', 'บรีฟลูกค้า'], ['company', 'ข้อมูลบริษัท'],
                ['services', 'บริการ'], ['packages', 'แพ็กเกจ'], ['portfolios', 'ผลงาน'], ['articles', 'ข่าวสาร'],
                ...(user.role === 'admin' ? [['users', 'ผู้ใช้งาน']] : []),
            ];
            const nav = el('nav', { class: 'content-tabs', 'aria-label': 'เมนูระบบหลังบ้าน' }, tabs.map(([key, label]) => button(label, () => goTo(key), active === key ? 'active' : '')));
            const views = { overview: overviewView, projects: projectsView, inquiries: inquiriesView, company: companyView, services: servicesView, packages: packagesView, portfolios: portfoliosView, articles: articlesView, users: usersView };
            const content = await (views[active] || overviewView)();
            root.replaceChildren(shell(el('section', { class: 'main-content' },
                el('div', { class: 'page-heading' }, el('div', {}, el('p', { class: 'eyebrow' }, 'CONTENT & OPERATIONS'), el('h1', {}, 'ระบบบริหารเว็บไซต์ MONSTOPIA'), el('p', { class: 'muted' }, `เข้าสู่ระบบในฐานะ ${user.role}`)), el('a', { class: 'button small', href: '/', target: '_blank', rel: 'noopener' }, 'ดูหน้าเว็บไซต์', arrowIcon())),
                nav,
                content
            )));
        } catch (problem) {
            root.replaceChildren(el('section', { class: 'brief-shell panel' }, el('h2', {}, 'โหลดข้อมูลไม่สำเร็จ'), el('p', {}, problem.message), button('ลองอีกครั้ง', renderDashboard, 'primary')));
        }
    }

    async function overviewView() {
        const data = (await api('/api/admin/dashboard')).data;
        const metrics = [['โครงการที่กำลังทำ', data.projects, 'projects'], ['บรีฟที่รอติดต่อ', data.pending_inquiries, 'inquiries'], ['บริการ', data.services, 'services'], ['แพ็กเกจ', data.packages, 'packages'], ['ผลงาน', data.portfolios, 'portfolios'], ['ข่าวที่เผยแพร่', data.published_articles, 'articles']];
        const setupComplete = [data.company_profiles, data.services, data.packages, data.portfolios].filter(Boolean).length;
        return el('section', {},
            el('div', { class: 'overview-head panel' }, el('div', {}, el('p', { class: 'eyebrow' }, 'WEBSITE READINESS'), el('h2', {}, 'ศูนย์ควบคุมข้อมูลเว็บไซต์'), el('p', { class: 'muted' }, 'แก้ไขข้อมูลจากหลังบ้าน แล้วหน้าเว็บไซต์จะดึงข้อมูลที่เผยแพร่ไปแสดงโดยอัตโนมัติ')), el('div', { class: 'readiness' }, el('strong', {}, `${setupComplete}/4`), el('span', {}, 'หมวดหลักมีข้อมูล'))),
            el('div', { class: 'dashboard-metrics' }, metrics.map(([label, value, key]) => metricCard(label, value, key))),
            el('div', { class: 'two-columns dashboard-columns' },
                el('section', { class: 'panel' }, el('div', { class: 'section-heading' }, el('h2', {}, 'บรีฟล่าสุด'), button('ดูทั้งหมด', () => goTo('inquiries'), 'small')), data.recent_inquiries.length ? el('ul', { class: 'activity-list' }, data.recent_inquiries.map(item => el('li', {}, el('div', {}, el('strong', {}, item.client_name), el('span', {}, item.budget_range)), badge(item.status, item.status)))) : el('p', { class: 'empty' }, 'ยังไม่มีบรีฟ')),
                el('section', { class: 'panel' }, el('div', { class: 'section-heading' }, el('h2', {}, 'เริ่มจัดการเนื้อหา')), el('div', { class: 'quick-actions' }, button('ข้อมูลบริษัท', () => goTo('company')), button('เพิ่มบริการ', () => serviceForm()), button('เพิ่มแพ็กเกจ', () => packageForm()), button('เขียนข่าวสาร', () => articleForm())))
            )
        );
    }

    function metricCard(label, value, key) {
        const card = button('', () => goTo(key), 'metric-card');
        card.append(el('span', { class: 'metric-label' }, label), el('strong', {}, value), el('small', { class: 'metric-action' }, 'เปิดดูและจัดการ', el('span', { class: 'icon-next', 'aria-hidden': 'true' })));
        return card;
    }

    async function projectsView() {
        const [projectsResponse, clientsResponse] = await Promise.all([api('/api/admin/projects'), api('/api/admin/clients')]);
        const projects = projectsResponse.data;
        const clients = clientsResponse.data;
        const cards = projects.map(project => row(project.project_name, `${project.client_name} · ${project.client?.email || 'ยังไม่ผูกบัญชี'} · ${Number(project.total_budget).toLocaleString('th-TH')} บาท · ${project.milestones_count} งวด`, badge(project.status, project.status), [button('แก้ไข', () => projectForm(project, clients), 'small'), button('จัดการ Milestone', () => manageMilestones(project), 'small'), ...(project.status !== 'archived' ? [button('เก็บถาวร', () => archiveProject(project), 'small danger')] : [])]));
        return el('section', {}, el('div', { class: 'section-heading' }, el('h2', {}, 'โครงการที่ตกลงดำเนินงาน'), button('+ เปิดโครงการ', () => projectForm(null, clients), 'primary small')), clients.length ? null : el('div', { class: 'note' }, 'ยังไม่มีบัญชี Client — ผู้ดูแลระบบต้องสร้างบัญชีลูกค้าก่อนเปิดโครงการ'), cards.length ? el('div', { class: 'request-grid' }, cards) : el('div', { class: 'panel empty' }, 'ยังไม่มีโครงการ'));
    }

    function projectForm(item, clients) {
        const clientOptions = clients.map(client => [String(client.id), `${client.name} — ${client.email}`]);
        openForm(item ? 'แก้ไขโครงการ' : 'เปิดโครงการใหม่', [['project_name', 'ชื่อโครงการ'], ['client_name', 'ชื่อลูกค้า'], ['client_user_id', 'บัญชีลูกค้า', 'text', clientOptions], ['inquiry_id', 'รหัสบรีฟตั้งต้น', 'number', null, false], ['total_budget', 'มูลค่าโครงการ', 'number'], ['status', 'สถานะ', 'text', [['active', 'กำลังดำเนินงาน'], ['completed', 'เสร็จสิ้น'], ['archived', 'เก็บถาวร']]], ['start_date', 'วันเริ่ม', 'date'], ['end_date', 'วันสิ้นสุด', 'date', null, false]], data => api(`/api/admin/projects${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, client_user_id: Number(data.client_user_id), inquiry_id: data.inquiry_id ? Number(data.inquiry_id) : null, total_budget: Number(data.total_budget), end_date: data.end_date || null }) }), item);
    }

    async function archiveProject(project) {
        if (!confirm(`เก็บโครงการ “${project.project_name}” เป็นรายการถาวร?`)) return;
        await api(`/api/admin/projects/${project.id}`, { method: 'DELETE' });
        flash('เก็บโครงการถาวรแล้ว');
        renderDashboard();
    }

    async function inquiriesView() {
        const inquiries = (await api('/api/admin/inquiries')).data.data;
        return section('บรีฟและรายการติดต่อ', null, null, inquiries.map(item => row(`#${String(item.id).padStart(6, '0')} · ${item.client_name}`, `${item.client_email} · ${item.client_phone}\nงบประมาณ: ${item.budget_range}\n\n${item.project_scope}`, badge(item.status, item.status), [button('ตอบกลับ', () => openForm('ตอบกลับบรีฟ', [['status', 'สถานะ', 'text', [['pending', 'รอดำเนินการ'], ['contacted', 'ติดต่อแล้ว'], ['accepted', 'รับดำเนินงาน'], ['rejected', 'ไม่รับดำเนินงาน']]], ['reply_message', 'ข้อความตอบกลับ', 'textarea']], data => api(`/api/admin/inquiries/${item.id}/reply`, { method: 'POST', body: JSON.stringify(data) }), item), 'small')])), 'ยังไม่มีบรีฟ');
    }

    async function companyView() {
        const items = (await api('/api/admin/company-profiles')).data;
        return section('ข้อมูลบริษัท', 'เพิ่มชุดข้อมูล', () => companyForm(), items.map(item => row(item.name, `${item.tagline || ''}\n${item.description}\n\n${item.email}${item.phone ? ` · ${item.phone}` : ''}\n${item.address}\nเลขทะเบียน: ${item.registration_number || '-'}`, publishedBadge(item.is_published), [button('แก้ไข', () => companyForm(item), 'small'), button('ลบ', () => removeRecord(`/api/admin/company-profiles/${item.id}`, item.name), 'small danger')])), 'ยังไม่มีข้อมูลบริษัท');
    }

    function companyForm(item = null) {
        openForm(item ? 'แก้ไขข้อมูลบริษัท' : 'เพิ่มข้อมูลบริษัท', [['name', 'ชื่อบริษัท'], ['tagline', 'ข้อความแนะนำสั้น', 'text', null, false], ['description', 'รายละเอียดบริษัท', 'textarea'], ['email', 'อีเมล', 'email'], ['phone', 'เบอร์โทร', 'tel', null, false], ['address', 'ที่อยู่', 'textarea'], ['registration_number', 'เลขทะเบียนนิติบุคคล', 'text', null, false], ['is_published', 'การแสดงผล', 'text', yesNoOptions]], data => api(`/api/admin/company-profiles${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, is_published: data.is_published === '1' }) }), item);
    }

    async function servicesView() {
        const items = (await api('/api/admin/services')).data;
        return section('บริการของบริษัท', 'เพิ่มบริการ', () => serviceForm(), items.map(item => row(item.title, `${item.short_description}\n${item.description}\nSlug: ${item.slug} · แพ็กเกจที่เชื่อม: ${item.packages_count}`, publishedBadge(item.is_published), [button('แก้ไข', () => serviceForm(item), 'small'), button('ลบ', () => removeRecord(`/api/admin/services/${item.id}`, item.title), 'small danger')])), 'ยังไม่มีข้อมูลบริการ');
    }

    function serviceForm(item = null) {
        openForm(item ? 'แก้ไขบริการ' : 'เพิ่มบริการ', [['title', 'ชื่อบริการ'], ['slug', 'Slug ภาษาอังกฤษ เช่น web-application'], ['short_description', 'คำอธิบายสั้น'], ['description', 'รายละเอียดบริการ', 'textarea'], ['icon_label', 'อักษรย่อ', 'text', null, false], ['display_order', 'ลำดับการแสดง', 'number'], ['is_published', 'การแสดงผล', 'text', yesNoOptions]], data => api(`/api/admin/services${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, display_order: Number(data.display_order), is_published: data.is_published === '1' }) }), item || { display_order: 0, is_published: true });
    }

    async function packagesView() {
        const [packageResponse, serviceResponse] = await Promise.all([api('/api/admin/service-packages'), api('/api/admin/services')]);
        const items = packageResponse.data;
        const services = serviceResponse.data;
        return section('แพ็กเกจและราคา', 'เพิ่มแพ็กเกจ', () => packageForm(null, services), items.map(item => row(item.name, `${item.service?.title || 'ไม่ระบุบริการ'} · ${item.price_label}${item.delivery_time ? ` · ${item.delivery_time}` : ''}\n${item.description}\n\n${item.features || ''}`, el('span', { class: 'page-meta' }, publishedBadge(item.is_published), item.is_featured ? badge('แนะนำ', 'submitted') : null), [button('แก้ไข', () => packageForm(item, services), 'small'), button('ลบ', () => removeRecord(`/api/admin/service-packages/${item.id}`, item.name), 'small danger')])), 'ยังไม่มีแพ็กเกจ');
    }

    async function packageForm(item = null, knownServices = null) {
        const services = knownServices || (await api('/api/admin/services')).data;
        const serviceOptions = [['', 'ไม่ระบุบริการ'], ...services.map(service => [String(service.id), service.title])];
        openForm(item ? 'แก้ไขแพ็กเกจ' : 'เพิ่มแพ็กเกจ', [['service_id', 'บริการที่เกี่ยวข้อง', 'text', serviceOptions, false], ['name', 'ชื่อแพ็กเกจ'], ['price_label', 'ราคา เช่น เริ่มต้น 50,000 บาท'], ['delivery_time', 'ระยะเวลาดำเนินงาน', 'text', null, false], ['description', 'รายละเอียด', 'textarea'], ['features', 'สิ่งที่ได้รับ (หนึ่งรายการต่อหนึ่งบรรทัด)', 'textarea', null, false], ['is_featured', 'ป้ายแนะนำ', 'text', [['0', 'แพ็กเกจทั่วไป'], ['1', 'แพ็กเกจแนะนำ']]], ['is_published', 'การแสดงผล', 'text', yesNoOptions], ['display_order', 'ลำดับการแสดง', 'number']], data => api(`/api/admin/service-packages${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, service_id: data.service_id ? Number(data.service_id) : null, is_featured: data.is_featured === '1', is_published: data.is_published === '1', display_order: Number(data.display_order) }) }), item || { is_featured: false, is_published: true, display_order: 0 });
    }

    async function portfoliosView() {
        const items = (await api('/api/portfolios')).data;
        return section('คลังผลงาน', 'เพิ่มผลงาน', () => portfolioForm(), items.map(item => row(item.title, `${item.category}\n${item.description}\nเทคโนโลยี: ${item.technologies || '-'}\nภาพหน้าจอ: ${item.image_url}`, badge('เผยแพร่', 'approved'), [button('แก้ไข', () => portfolioForm(item), 'small'), button('ลบ', () => removeRecord(`/api/admin/portfolios/${item.id}`, item.title), 'small danger')])), 'ยังไม่มีผลงานที่บันทึก');
    }

    function portfolioForm(item = null) {
        openForm(item ? 'แก้ไขผลงาน' : 'เพิ่มผลงาน', [['title', 'ชื่อผลงาน'], ['category', 'หมวดหมู่'], ['description', 'รายละเอียด', 'textarea'], ['image_url', 'URL ภาพหน้าจอจริง', 'url'], ['technologies', 'เทคโนโลยี', 'text', null, false]], data => api(`/api/admin/portfolios${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify(data) }), item);
    }

    async function articlesView() {
        const items = (await api('/api/admin/articles')).data;
        return section('ข่าวสารและบทความ', 'เขียนบทความ', () => articleForm(), items.map(item => row(item.title, `${item.excerpt}\nSlug: ${item.slug}\nกำหนดเผยแพร่: ${dateTime(item.published_at)}`, badge(item.status === 'published' ? 'เผยแพร่' : 'ฉบับร่าง', item.status === 'published' ? 'approved' : 'draft'), [button('แก้ไข', () => articleForm(item), 'small'), button('ลบ', () => removeRecord(`/api/admin/articles/${item.id}`, item.title), 'small danger')])), 'ยังไม่มีข่าวสารหรือบทความ');
    }

    function articleForm(item = null) {
        openForm(item ? 'แก้ไขบทความ' : 'เขียนบทความ', [['title', 'ชื่อบทความ'], ['slug', 'Slug ภาษาอังกฤษ'], ['excerpt', 'ข้อความเกริ่นนำ'], ['content', 'เนื้อหาบทความ', 'textarea'], ['image_url', 'URL รูปภาพ (ถ้ามี)', 'url', null, false], ['status', 'สถานะ', 'text', [['draft', 'ฉบับร่าง'], ['published', 'เผยแพร่']]], ['published_at', 'วันและเวลาเผยแพร่', 'datetime-local', null, false]], data => api(`/api/admin/articles${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, image_url: data.image_url || null, published_at: data.published_at || null }) }), item || { status: 'draft' });
    }

    async function usersView() {
        const users = (await api('/api/admin/users')).data;
        return section('ผู้ใช้งานและบทบาท', 'สร้างบัญชี', () => userForm(), users.map(item => row(item.name, `${item.email}\n${item.phone || 'ไม่ระบุเบอร์โทร'}`, badge(item.role, item.role), [button('แก้ไข', () => userForm(item), 'small'), ...(item.id !== user.id ? [button('ลบ', () => removeRecord(`/api/admin/users/${item.id}`, item.name), 'small danger')] : [])])), 'ยังไม่มีผู้ใช้งาน');
    }

    function userForm(item = null) {
        openForm(item ? 'แก้ไขบัญชี' : 'สร้างบัญชี', [['name', 'ชื่อ'], ['email', 'อีเมล', 'email'], ['password', item ? 'รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : 'รหัสผ่านอย่างน้อย 12 ตัวอักษร', 'password', null, !item], ['role', 'บทบาท', 'text', [['client', 'Client'], ['staff', 'Staff'], ['admin', 'Administrator']]], ['phone', 'เบอร์โทร', 'tel', null, false]], data => api(`/api/admin/users${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, password: data.password || null }) }), item);
    }

    async function manageMilestones(project) {
        const items = (await api(`/api/admin/projects/${project.id}/milestones`)).data;
        const timeline = items.length ? el('ol', { class: 'timeline' }, items.map(item => el('li', { class: 'timeline-item' }, el('span', { class: `timeline-dot ${item.status}` }), el('h3', {}, item.title), badge(item.status, item.status), el('p', {}, item.description || ''), el('span', { class: 'caption' }, `กำหนด ${date(item.due_date)}`), el('div', { class: 'actions' }, button('แก้ไขสถานะ', () => milestoneForm(project, item), 'small'))))) : el('p', { class: 'empty' }, 'ยังไม่มี Milestone');
        dialog.replaceChildren(el('header', {}, el('h2', {}, 'จัดการ Milestone'), button('ปิด ×', () => dialog.close(), 'small')), el('div', {}, el('div', { class: 'section-heading' }, el('h2', {}, project.project_name), button('+ เพิ่มงวดงาน', () => milestoneForm(project), 'primary small')), timeline));
        dialog.showModal();
    }

    function milestoneForm(project, item = null) {
        openForm(item ? 'อัปเดต Milestone' : 'เพิ่ม Milestone', [['title', 'ชื่องวดงาน'], ['description', 'รายละเอียด', 'textarea', null, false], ['due_date', 'กำหนดส่ง', 'date'], ['status', 'สถานะ', 'text', [['pending', 'รอดำเนินการ'], ['in_progress', 'กำลังดำเนินการ'], ['delivered', 'ส่งมอบแล้ว'], ['approved', 'อนุมัติแล้ว']]]], data => api(`/api/admin/projects/${project.id}/milestones${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify(data) }), item);
    }

    function showMilestones(project) {
        dialog.replaceChildren(el('header', {}, el('h2', {}, project.project_name), button('ปิด ×', () => dialog.close(), 'small')), project.milestones.length ? el('ol', { class: 'timeline' }, project.milestones.map(item => el('li', { class: 'timeline-item' }, el('span', { class: `timeline-dot ${item.status}` }), el('h3', {}, item.title), badge(item.status, item.status), el('p', {}, item.description || ''), el('span', { class: 'caption' }, `กำหนด ${date(item.due_date)}`)))) : el('p', { class: 'empty' }, 'ยังไม่มี Milestone'));
        dialog.showModal();
    }

    function date(value) {
        return value ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium' }).format(new Date(value)) : '—';
    }

    function dateTime(value) {
        return value ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'เผยแพร่ทันที';
    }

    (async () => {
        try {
            user = (await api('/api/auth/me')).user;
            if (user) renderDashboard(); else login();
        } catch {
            login();
        }
    })();
});
