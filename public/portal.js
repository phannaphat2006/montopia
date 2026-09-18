document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('workspace');
    const dialog = document.getElementById('workspace-dialog');
    const notice = document.getElementById('notice');
    let csrf = '';
    let user = null;
    let active = 'overview';
    let inquiryPage = 1;

    const projectStatuses = [
        ['planned', 'รอเริ่ม'],
        ['in_progress', 'กำลังทำ'],
        ['review', 'รอตรวจ'],
        ['completed', 'เสร็จแล้ว'],
        ['archived', 'เก็บถาวร'],
    ];
    const milestoneStatuses = [
        ['pending', 'รอเริ่ม'],
        ['in_progress', 'กำลังทำ'],
        ['delivered', 'ส่งให้ตรวจ'],
        ['approved', 'อนุมัติแล้ว'],
    ];
    const statusLabels = Object.fromEntries([
        ...projectStatuses,
        ...milestoneStatuses,
        ['contacted', 'ติดต่อแล้ว'],
        ['accepted', 'รับดำเนินงาน'],
        ['rejected', 'ไม่รับดำเนินงาน'],
        ['admin', 'ผู้ดูแลระบบ'],
        ['staff', 'ทีมงาน'],
        ['client', 'ลูกค้า'],
    ]);

    const el = (tag, props = {}, ...children) => {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(props)) {
            if (key === 'class') node.className = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value);
            else if (value !== undefined && value !== null) node.setAttribute(key, value);
        }
        for (const child of children.flat(Infinity)) {
            if (child !== null && child !== undefined) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
        }
        return node;
    };

    const button = (text, onClick, classes = '') => el('button', { type: 'button', class: classes, onClick }, text);
    const badge = (status, classes = status) => el('span', { class: `badge ${classes}` }, statusLabels[status] || status);
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
        const body = await response.json().catch(() => ({}));
        if (!response.ok || !body.token) throw new Error('ไม่สามารถยืนยันคำขอได้ กรุณารีเฟรชหน้าแล้วลองใหม่');
        csrf = body.token;
        return csrf;
    }

    async function api(url, options = {}) {
        const method = options.method || 'GET';
        const isFormData = options.body instanceof FormData;
        const headers = {
            Accept: 'application/json',
            ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': await token() } : {}),
            ...options.headers,
        };
        const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });
        if (response.status === 204) return null;
        if (response.status === 419) {
            csrf = '';
            throw new Error('การยืนยันคำขอหมดอายุ กรุณาลองดำเนินการอีกครั้ง หากยังไม่ได้ให้รีเฟรชหน้าและเข้าสู่ระบบใหม่');
        }
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
            input = el('input', { name, type, required: required ? '' : null, maxlength: ['number', 'date', 'datetime-local', 'file'].includes(type) ? null : 255 });
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

    function showDialog(...content) {
        dialog.replaceChildren(...content);
        if (!dialog.open) dialog.showModal();
    }

    function askConfirmation(title, message, confirmLabel = 'ยืนยัน') {
        return new Promise(resolve => {
            let settled = false;
            const finish = confirmed => {
                if (settled) return;
                settled = true;
                dialog.removeEventListener('close', onClose);
                dialog.close();
                resolve(confirmed);
            };
            const onClose = () => finish(false);
            showDialog(el('header', {}, el('h2', {}, title)), el('p', { class: 'note' }, message),
                el('div', { class: 'actions' }, button('ยกเลิก', () => finish(false)), button(confirmLabel, () => finish(true), 'primary')));
            dialog.addEventListener('close', onClose, { once: true });
        });
    }

    function openForm(title, fields, submit, values = null, afterSave = renderDashboard) {
        const form = el('form');
        const error = el('p', { class: 'form-error', role: 'alert' });
        fields.forEach(item => form.append(field(...item)));
        const save = el('button', { type: 'submit', class: 'primary' }, 'บันทึกข้อมูล');
        form.append(error, el('div', { class: 'actions' }, button('ยกเลิก', () => dialog.close()), save));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            save.disabled = true;
            save.textContent = 'กำลังบันทึก…';
            error.textContent = '';
            try {
                const result = await submit(Object.fromEntries(new FormData(form)));
                dialog.close();
                flash('บันทึกข้อมูลเรียบร้อย');
                await afterSave(result);
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                save.disabled = false;
                save.textContent = 'บันทึกข้อมูล';
            }
        });
        showDialog(el('header', {}, el('h2', {}, title), button('ปิด ×', () => dialog.close(), 'small')), form);
        fillForm(values);
    }

    async function removeRecord(url, label) {
        if (!await askConfirmation('ยืนยันการลบข้อมูล', `ยืนยันการลบ “${label}” ออกจากระบบ?`, 'ยืนยันการลบ')) return;
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
            submit.textContent = 'กำลังตรวจสอบ…';
            error.textContent = '';
            try {
                user = (await api('/api/auth/login', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) })).user;
                // Laravel rotates the CSRF token when regenerating the authenticated session.
                csrf = '';
                active = user.role === 'client' ? 'projects' : 'overview';
                user.must_change_password ? passwordGate() : renderDashboard();
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                submit.disabled = false;
                submit.textContent = 'เข้าสู่ระบบ';
            }
        });
        root.replaceChildren(el('div', { class: 'login-layout' },
            el('section', { class: 'login-intro' }, el('p', { class: 'eyebrow' }, 'MONSTOPIA INFORMATION SYSTEM'), el('h1', {}, 'จัดการงานตั้งแต่รับบรีฟ', el('br'), 'จนถึงส่งมอบโครงการ'), el('p', { class: 'muted' }, 'พื้นที่ทำงานสำหรับผู้ดูแล ทีมงาน และลูกค้า โดยทุกบัญชีเห็นข้อมูลตามสิทธิ์ของตนเอง')),
            el('section', { class: 'panel login-card' }, el('p', { class: 'eyebrow' }, 'SECURE ACCESS'), el('h2', {}, 'เข้าสู่ระบบ'), el('p', { class: 'muted' }, 'ใช้บัญชีที่ผู้ดูแลระบบสร้างให้ ไม่มีระบบสมัครสมาชิกสาธารณะ'), form, el('p', { class: 'caption' }, 'ระบบจำกัดการลองรหัสผ่านผิดและตรวจสิทธิ์ทุก API จาก Laravel'))
        ));
    }

    function passwordFields() {
        return [
            field('current_password', 'รหัสผ่านปัจจุบัน', 'password'),
            field('password', 'รหัสผ่านใหม่อย่างน้อย 12 ตัว มีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข', 'password'),
            field('password_confirmation', 'ยืนยันรหัสผ่านใหม่', 'password'),
        ];
    }

    function passwordGate() {
        const form = el('form', {}, ...passwordFields());
        const error = el('p', { class: 'form-error', role: 'alert' });
        const save = el('button', { type: 'submit', class: 'primary' }, 'ตั้งรหัสผ่านใหม่');
        form.append(error, el('div', { class: 'actions password-gate-actions' }, button('ออกจากระบบ', logout), save));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            save.disabled = true;
            error.textContent = '';
            try {
                await api('/api/account/password', { method: 'PUT', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
                user.must_change_password = false;
                csrf = '';
                flash('ตั้งรหัสผ่านใหม่เรียบร้อย');
                renderDashboard();
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                save.disabled = false;
            }
        });
        root.replaceChildren(el('div', { class: 'login-layout single-gate' },
            el('section', { class: 'login-intro' }, el('p', { class: 'eyebrow' }, 'FIRST SIGN IN'), el('h1', {}, 'ตั้งรหัสผ่านของคุณ', el('br'), 'ก่อนเริ่มใช้งาน'), el('p', { class: 'muted' }, 'รหัสผ่านชั่วคราวใช้สำหรับเข้าสู่ระบบครั้งแรกเท่านั้น')),
            el('section', { class: 'panel login-card' }, el('h2', {}, 'เปลี่ยนรหัสผ่าน'), form)
        ));
    }

    function changePasswordDialog() {
        const form = el('form', {}, ...passwordFields());
        const error = el('p', { class: 'form-error', role: 'alert' });
        const save = el('button', { type: 'submit', class: 'primary' }, 'เปลี่ยนรหัสผ่าน');
        form.append(error, el('div', { class: 'actions' }, button('ยกเลิก', () => dialog.close()), save));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            save.disabled = true;
            error.textContent = '';
            try {
                await api('/api/account/password', { method: 'PUT', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
                dialog.close();
                csrf = '';
                flash('เปลี่ยนรหัสผ่านเรียบร้อย');
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                save.disabled = false;
            }
        });
        showDialog(el('header', {}, el('h2', {}, 'เปลี่ยนรหัสผ่าน'), button('ปิด ×', () => dialog.close(), 'small')), form);
    }

    async function logout() {
        try { await api('/api/auth/logout', { method: 'POST' }); } finally {
            user = null;
            csrf = '';
            inquiryPage = 1;
            if (dialog.open) dialog.close();
            dialog.replaceChildren();
            notice.hidden = true;
            notice.textContent = '';
            login();
        }
    }

    const adminTabs = [
        ['overview', 'ภาพรวม', 'งานประจำ'],
        ['inquiries', 'บรีฟลูกค้า', 'งานประจำ'],
        ['projects', 'โครงการ', 'งานประจำ'],
        ['users', 'ผู้ใช้งาน', 'งานประจำ'],
        ['company', 'ข้อมูลบริษัท', 'เว็บไซต์'],
        ['services', 'บริการ', 'เว็บไซต์'],
        ['packages', 'แพ็กเกจ', 'เว็บไซต์'],
        ['portfolios', 'ผลงาน', 'เว็บไซต์'],
        ['articles', 'บทความ', 'เว็บไซต์'],
    ];

    function navigation() {
        if (user.role === 'client') return null;
        const allowed = adminTabs.filter(([key]) => user.role === 'admin' || key !== 'users');
        const groups = [...new Set(allowed.map(([, , group]) => group))];
        return el('nav', { class: 'side-nav', 'aria-label': 'เมนูระบบหลังบ้าน' }, groups.map(group =>
            el('div', { class: 'side-nav-group' }, el('span', {}, group), allowed.filter(([, , itemGroup]) => itemGroup === group).map(([key, label]) => button(label, () => goTo(key), active === key ? 'active' : '')))
        ));
    }

    function shell(content) {
        return el('div', { class: 'workspace-shell' },
            el('aside', { class: 'sidebar' },
                el('div', { class: 'account-summary' }, el('p', { class: 'eyebrow' }, 'ACCOUNT'), el('strong', {}, user.name), el('p', { class: 'caption' }, user.email, el('br'), statusLabels[user.role] || user.role)),
                navigation(),
                el('div', { class: 'account' }, button('เปลี่ยนรหัสผ่าน', changePasswordDialog, 'small'), button('ออกจากระบบ', logout, 'small'))
            ),
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
        root.replaceChildren(el('div', { class: 'loading-state', role: 'status' }, el('span', { class: 'spinner', 'aria-hidden': 'true' }), el('p', {}, 'กำลังโหลดข้อมูลล่าสุด…')));
        try {
            if (user.role === 'client') {
                await renderClientWorkspace();
                return;
            }
            const views = { overview: overviewView, projects: projectsView, inquiries: inquiriesView, company: companyView, services: servicesView, packages: packagesView, portfolios: portfoliosView, articles: articlesView, users: usersView };
            const content = await (views[active] || overviewView)();
            root.replaceChildren(shell(el('section', { class: 'main-content' },
                el('div', { class: 'page-heading' }, el('div', {}, el('p', { class: 'eyebrow' }, 'PROJECT OPERATIONS'), el('h1', {}, 'ระบบบริหาร MONSTOPIA'), el('p', { class: 'muted' }, 'รับบรีฟ ดูแลลูกค้า และติดตามการส่งมอบจากที่เดียว')), el('a', { class: 'button small', href: '/', target: '_blank', rel: 'noopener' }, 'ดูหน้าเว็บไซต์', arrowIcon())),
                content
            )));
        } catch (problem) {
            root.replaceChildren(el('section', { class: 'brief-shell panel' }, el('h2', {}, 'โหลดข้อมูลไม่สำเร็จ'), el('p', {}, problem.message), button('ลองอีกครั้ง', renderDashboard, 'primary')));
        }
    }

    async function renderClientWorkspace() {
        const projects = (await api('/api/client/projects')).data;
        const content = el('section', { class: 'main-content' },
            el('div', { class: 'page-heading' }, el('div', {}, el('p', { class: 'eyebrow' }, 'CLIENT WORKSPACE'), el('h1', {}, 'ความคืบหน้าโครงการของคุณ'), el('p', { class: 'muted' }, `แสดงเฉพาะโครงการของบัญชี ${user.email}`))),
            projects.length ? el('div', { class: 'project-grid' }, projects.map(clientProjectCard)) : el('div', { class: 'panel empty' }, 'ยังไม่มีโครงการที่ผูกกับบัญชีนี้')
        );
        root.replaceChildren(shell(content));
    }

    function clientProjectCard(project) {
        return el('article', { class: 'panel project-card' },
            el('div', { class: 'project-card-head' }, el('div', {}, el('p', { class: 'eyebrow' }, `PROJECT #${String(project.id).padStart(4, '0')}`), el('h2', {}, project.project_name)), badge(project.status)),
            progressMeter(project.progress_percent),
            el('dl', { class: 'project-facts' }, fact('เริ่มงาน', date(project.start_date)), fact('กำหนดส่ง', date(project.end_date)), fact('Milestone', `${project.milestones.length} รายการ`), fact('ไฟล์งาน', `${project.attachments.length} ไฟล์`)),
            button('ดูรายละเอียดโครงการ', () => showClientProject(project), 'primary')
        );
    }

    async function overviewView() {
        const data = (await api('/api/admin/dashboard')).data;
        const metrics = [
            ['บรีฟทั้งหมด', data.inquiries, 'inquiries'],
            ['โครงการทั้งหมด', data.all_projects, 'projects'],
            ['บัญชีลูกค้า', data.clients, 'users'],
            ['บรีฟที่รอติดต่อ', data.pending_inquiries, 'inquiries'],
            ['บริการบนเว็บไซต์', data.services, 'services'],
            ['ผลงาน', data.portfolios, 'portfolios'],
        ];
        return el('section', {},
            el('div', { class: 'overview-head panel' }, el('div', {}, el('p', { class: 'eyebrow' }, 'OPERATION OVERVIEW'), el('h2', {}, 'ภาพรวมการรับงานและโครงการ'), el('p', { class: 'muted' }, 'ตัวเลขสามรายการแรกคือหัวใจของระบบ: บรีฟที่เข้ามา โครงการที่เปิด และบัญชีลูกค้าที่เข้าดูงานได้')), el('div', { class: 'readiness' }, el('strong', {}, data.projects), el('span', {}, 'งานที่กำลังเดิน'))),
            el('div', { class: 'dashboard-metrics' }, metrics.map(([label, value, key]) => metricCard(label, value, key))),
            el('div', { class: 'two-columns dashboard-columns' },
                el('section', { class: 'panel' }, el('div', { class: 'section-heading' }, el('h2', {}, 'บรีฟล่าสุด'), button('ดูทั้งหมด', () => goTo('inquiries'), 'small')), data.recent_inquiries.length ? el('ul', { class: 'activity-list' }, data.recent_inquiries.map(item => el('li', {}, el('div', {}, el('strong', {}, item.client_name), el('span', {}, item.budget_range)), badge(item.status)))) : el('p', { class: 'empty' }, 'ยังไม่มีบรีฟ')),
                el('section', { class: 'panel' }, el('div', { class: 'section-heading' }, el('h2', {}, 'ทางลัดสำหรับงานประจำ')), el('div', { class: 'quick-actions' }, button('ตรวจบรีฟใหม่', () => goTo('inquiries')), button('เปิดโครงการ', () => goTo('projects')), user.role === 'admin' ? button('สร้างบัญชีลูกค้า', () => userForm(null, { role: 'client' })) : null, button('อัปเดตผลงาน', () => goTo('portfolios'))))
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
        const cards = projects.map(project => el('article', { class: 'panel project-card' },
            el('div', { class: 'project-card-head' }, el('div', {}, el('p', { class: 'eyebrow' }, project.client?.email || 'ยังไม่ผูกบัญชี'), el('h2', {}, project.project_name), el('p', { class: 'muted' }, project.client_name)), badge(project.status)),
            progressMeter(project.progress_percent),
            el('dl', { class: 'project-facts' }, fact('เริ่มงาน', date(project.start_date)), fact('กำหนดส่ง', date(project.end_date)), fact('มูลค่าโครงการ', Number(project.total_budget) === 0 ? 'รอระบุ / 0 บาท' : `${Number(project.total_budget).toLocaleString('th-TH')} บาท`), fact('ข้อมูลส่งมอบ', `${project.milestones_count} Milestone · ${project.attachments_count} ไฟล์`)),
            !project.client ? el('p', { class: 'note' }, 'รับงานแล้ว — ยังไม่ผูกบัญชี Client ลูกค้าจะยังไม่เห็นโครงการนี้ ให้เลือกบัญชีใน “แก้ไขข้อมูล”') : null,
            el('div', { class: 'actions' }, button('เปิดพื้นที่โครงการ', () => manageProject(project), 'primary small'), button('แก้ไขข้อมูล', () => projectForm(project, clients), 'small'), ...(project.status !== 'archived' ? [button('เก็บถาวร', () => archiveProject(project), 'small danger')] : []))
        ));
        return el('section', {}, el('div', { class: 'section-heading' }, el('h2', {}, 'โครงการและการส่งมอบ'), button('+ เปิดโครงการ', () => projectForm(null, clients), 'primary small')), clients.length ? null : el('div', { class: 'note' }, 'เปิดโครงการได้ก่อนมีบัญชีลูกค้า — ผู้ดูแลระบบต้องสร้างและผูกบัญชี Client ก่อนลูกค้าจะเข้าดูงานได้'), cards.length ? el('div', { class: 'project-grid' }, cards) : el('div', { class: 'panel empty' }, 'ยังไม่มีโครงการ'));
    }

    function projectForm(item, clients, preset = {}) {
        const clientOptions = [['', 'ยังไม่ผูกบัญชีลูกค้า'], ...clients.map(client => [String(client.id), `${client.name} — ${client.email}`])];
        const values = item || { status: 'planned', progress_percent: 0, start_date: new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Bangkok' }).format(new Date()), ...preset };
        openForm(item ? 'แก้ไขโครงการ' : 'เปิดโครงการใหม่', [['project_name', 'ชื่อโครงการ'], ['client_name', 'ชื่อลูกค้าหรือบริษัท'], ['client_user_id', 'บัญชีลูกค้าที่จะเข้าดูโครงการ (ผูกภายหลังได้)', 'text', clientOptions, false], ['inquiry_id', 'รหัสบรีฟตั้งต้น', 'number', null, false], ['total_budget', 'มูลค่าโครงการ (0 = รอระบุ / ไม่มีค่าใช้จ่าย)', 'number'], ['status', 'สถานะโครงการ', 'text', projectStatuses.filter(([value]) => value !== 'archived')], ['progress_percent', 'เปอร์เซ็นต์ความคืบหน้า 0–100', 'number'], ['start_date', 'วันเริ่ม (โครงการจากบรีฟตั้งต้นเป็นวันที่รับงาน)', 'date'], ['end_date', 'วันส่งงาน', 'date', null, false]], data => api(`/api/admin/projects${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, client_user_id: data.client_user_id ? Number(data.client_user_id) : null, inquiry_id: data.inquiry_id ? Number(data.inquiry_id) : null, total_budget: Number(data.total_budget), progress_percent: Number(data.progress_percent), end_date: data.end_date || null }) }), values);
    }

    async function archiveProject(project) {
        if (!await askConfirmation('เก็บโครงการถาวร', `เก็บโครงการ “${project.project_name}” เป็นรายการถาวร? ลูกค้าจะไม่เห็นโครงการนี้ใน Workspace`, 'ยืนยันเก็บถาวร')) return;
        try {
            await api(`/api/admin/projects/${project.id}`, { method: 'DELETE' });
            flash('เก็บโครงการถาวรแล้ว');
            renderDashboard();
        } catch (problem) {
            flash(problem.message, true);
        }
    }

    async function inquiriesView() {
        let [inquiryResponse, clientResponse] = await Promise.all([api(`/api/admin/inquiries?page=${inquiryPage}`), api('/api/admin/clients')]);
        if (inquiryResponse.data.current_page > inquiryResponse.data.last_page) {
            inquiryPage = inquiryResponse.data.last_page;
            inquiryResponse = await api(`/api/admin/inquiries?page=${inquiryPage}`);
        }
        const page = inquiryResponse.data;
        inquiryPage = page.current_page;
        const inquiries = page.data;
        const clients = clientResponse.data;
        const items = inquiries.map(item => {
            const client = clients.find(candidate => candidate.email.toLowerCase() === item.client_email.toLowerCase());
            const actions = [button('ตอบกลับลูกค้า', () => replyForm(item), 'small')];
            if (item.project) {
                actions.push(button('เปิดโครงการ', () => { active = 'projects'; renderDashboard(); }, 'small'));
            } else {
                actions.push(button('รับงานและเปิดโครงการ', event => acceptInquiry(item, event.currentTarget), 'primary small'));
            }
            if (!client && user.role === 'admin') actions.push(button('สร้างบัญชี Client', () => userForm(null, { name: item.client_name, email: item.client_email, phone: item.client_phone, role: 'client' }), 'small'));
            const replyHistory = item.replies.length ? `\n\nตอบกลับแล้ว ${item.replies.length} ครั้ง · ล่าสุดโดย ${item.replies.at(-1)?.user?.name || 'ทีมงาน'}` : '';
            return row(`#${String(item.id).padStart(6, '0')} · ${item.client_name}`, `${item.client_email} · ${item.client_phone}\nงบประมาณ: ${item.budget_range}\n\n${item.project_scope}${replyHistory}`, badge(item.status), actions);
        });
        const view = section('บรีฟและเส้นทางรับงาน', null, null, items, 'ยังไม่มีบรีฟจากลูกค้า');
        const previous = button('หน้าก่อนหน้า', () => { inquiryPage--; renderDashboard(); }, 'small');
        const next = button('หน้าถัดไป', () => { inquiryPage++; renderDashboard(); }, 'small');
        previous.disabled = page.current_page <= 1;
        next.disabled = page.current_page >= page.last_page;
        view.append(el('nav', { class: 'inquiry-pagination', 'aria-label': 'แบ่งหน้าบรีฟลูกค้า' },
            el('p', { role: 'status' }, `แสดง ${page.from || 0}–${page.to || 0} จาก ${page.total} รายการ · หน้า ${page.current_page}/${page.last_page}`),
            el('div', { class: 'actions' }, previous, next)));
        return view;
    }

    function replyForm(item) {
        openForm('ตอบกลับบรีฟ', [['status', 'สถานะการรับงาน', 'text', [['pending', 'รอดำเนินการ'], ['contacted', 'ติดต่อแล้ว'], ['accepted', 'รับงานและเปิดโครงการอัตโนมัติ'], ['rejected', 'ไม่รับดำเนินงาน']]], ['reply_message', 'ข้อความตอบกลับทางอีเมล', 'textarea']], data => api(`/api/admin/inquiries/${item.id}/reply`, { method: 'POST', body: JSON.stringify(data) }), item, async result => {
            if (result.project_id) active = 'projects';
            await renderDashboard();
            if (result.project_id) flash(result.project_created ? 'รับงานแล้ว โครงการถูกสร้างในหน้าโครงการเรียบร้อย' : 'รับงานแล้ว ใช้โครงการเดิมโดยไม่สร้างซ้ำ');
            if (!result.email_sent) flash('บันทึกสำเร็จ แต่ส่งอีเมลไม่สำเร็จ กรุณาตรวจการตั้งค่าอีเมล', true);
        });
    }

    async function acceptInquiry(item, control) {
        if (!await askConfirmation('ยืนยันรับงาน', `รับงานจาก “${item.client_name}” และเปิดโครงการอัตโนมัติ? โครงการจะรอเริ่มที่ 0% วันเริ่มตั้งต้นเป็นวันที่เปิดโครงการ มูลค่าเริ่มต้น 0 รอระบุ และยังไม่กำหนดวันส่งงาน`, 'ยืนยันรับงานและเปิดโครงการ')) return;
        control.disabled = true;
        control.textContent = 'กำลังรับงาน…';
        try {
            const result = await api(`/api/admin/inquiries/${item.id}/reply`, { method: 'POST', body: JSON.stringify({ status: 'accepted', reply_message: 'ทีมงานรับดำเนินงานตามบรีฟแล้ว และเปิดโครงการในระบบเพื่อเตรียมแผนงาน โดยจะประสานรายละเอียดขอบเขต งบประมาณ และกำหนดส่งงานต่อไป' }) });
            active = 'projects';
            await renderDashboard();
            flash(result.project_created ? 'รับงานและเปิดโครงการเรียบร้อย ไม่ต้องสร้างโครงการซ้ำ' : 'เปิดโครงการเดิมเรียบร้อย ไม่มีการสร้างซ้ำ');
            if (!result.email_sent) flash('รับงานและบันทึกโครงการสำเร็จ แต่ส่งอีเมลไม่สำเร็จ', true);
        } catch (problem) {
            flash(problem.message, true);
        } finally {
            control.disabled = false;
            control.textContent = 'รับงานและเปิดโครงการ';
        }
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
        return section('ผู้ใช้งานและสิทธิ์', 'สร้างบัญชี', () => userForm(), users.map(item => row(item.name, `${item.email}\n${item.phone || 'ไม่ระบุเบอร์โทร'}${item.last_login_at ? `\nเข้าสู่ระบบล่าสุด ${dateTime(item.last_login_at)}` : '\nยังไม่เคยเข้าสู่ระบบ'}`, el('span', { class: 'page-meta' }, badge(item.role), item.must_change_password ? badge('รอเปลี่ยนรหัสผ่าน', 'submitted') : null), [button('แก้ไข', () => userForm(item), 'small'), ...(item.id !== user.id ? [button('ลบ', () => removeRecord(`/api/admin/users/${item.id}`, item.name), 'small danger')] : [])])), 'ยังไม่มีผู้ใช้งาน');
    }

    function userForm(item = null, preset = {}) {
        const values = item || { role: 'client', ...preset };
        openForm(item ? 'แก้ไขบัญชี' : 'สร้างบัญชี', [['name', 'ชื่อที่แสดง'], ['email', 'อีเมลสำหรับเข้าสู่ระบบ', 'email'], ['password', item ? 'รหัสผ่านชั่วคราวใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : 'รหัสผ่านชั่วคราวอย่างน้อย 12 ตัวอักษร', 'password', null, !item], ['role', 'บทบาท', 'text', [['client', 'ลูกค้า'], ['staff', 'ทีมงาน'], ['admin', 'ผู้ดูแลระบบ']]], ['phone', 'เบอร์โทร', 'tel', null, false]], data => api(`/api/admin/users${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify({ ...data, password: data.password || null }) }), values);
    }

    async function manageProject(project) {
        try {
            const detail = (await api(`/api/admin/projects/${project.id}`)).data;
            const milestoneList = detail.milestones.length ? el('ol', { class: 'timeline' }, detail.milestones.map(item => milestoneItem(detail, item, true))) : el('p', { class: 'empty' }, 'ยังไม่มี Milestone');
            const files = detail.attachments.length ? el('ul', { class: 'file-list' }, detail.attachments.map(item => fileItem(detail, item, true))) : el('p', { class: 'empty' }, 'ยังไม่มีไฟล์ในโครงการ');
            const updates = detail.updates.length ? el('ol', { class: 'update-list' }, detail.updates.map(item => teamUpdateItem(detail, item))) : el('p', { class: 'empty' }, 'ยังไม่มีประวัติการอัปเดต');
            showDialog(
                el('header', {}, el('div', {}, el('p', { class: 'eyebrow' }, `PROJECT #${String(detail.id).padStart(4, '0')}`), el('h2', {}, detail.project_name)), button('ปิด ×', () => { dialog.close(); renderDashboard(); }, 'small')),
                el('div', { class: 'project-dialog-summary' }, el('div', {}, badge(detail.status), el('span', {}, `${detail.client_name} · ${detail.client?.email || '-'}`)), progressMeter(detail.progress_percent)),
                el('div', { class: 'project-tools' }, button('+ บันทึกความคืบหน้า', () => progressUpdateForm(detail), 'primary small'), button('+ เพิ่ม Milestone', () => milestoneForm(detail), 'small'), button('+ แนบไฟล์', () => uploadForm(detail), 'small')),
                el('div', { class: 'project-detail-grid' },
                    el('section', { class: 'detail-section' }, el('h3', {}, 'Milestone และกำหนดส่ง'), milestoneList),
                    el('section', { class: 'detail-section' }, el('h3', {}, 'ไฟล์งาน'), files)
                ),
                el('section', { class: 'detail-section' }, el('h3', {}, 'ประวัติการอัปเดต'), updates)
            );
        } catch (problem) {
            flash(problem.message, true);
        }
    }

    function progressUpdateForm(project) {
        openForm('บันทึกความคืบหน้า', [['title', 'หัวข้ออัปเดต'], ['body', 'รายละเอียดที่ลูกค้าควรรู้', 'textarea', null, false], ['progress_percent', 'ความคืบหน้า 0–100 (เว้นว่างถ้าไม่เปลี่ยน)', 'number', null, false], ['status', 'สถานะโครงการ', 'text', [['', 'ไม่เปลี่ยนสถานะ'], ...projectStatuses.filter(([value]) => value !== 'archived')], false], ['visible_to_client', 'การมองเห็น', 'text', [['1', 'ลูกค้าเห็นและได้รับอีเมล'], ['0', 'บันทึกภายในทีม']]]], data => api(`/api/admin/projects/${project.id}/updates`, { method: 'POST', body: JSON.stringify({ ...data, progress_percent: data.progress_percent === '' ? null : Number(data.progress_percent), status: data.status || null, visible_to_client: data.visible_to_client === '1' }) }), { progress_percent: project.progress_percent, status: '', visible_to_client: true }, async result => {
            await manageProject(project);
            if (result.data.email_status === 'failed') flash('บันทึกความคืบหน้าแล้ว แต่ส่งอีเมลไม่สำเร็จ ดูสถานะและลองส่งใหม่ในประวัติการอัปเดต', true);
            if (result.data.email_status === 'simulated') flash('บันทึกความคืบหน้าแล้ว อีเมลอยู่ในโหมดทดสอบ ยังไม่ได้ส่งออกจริง');
        });
    }

    function teamUpdateItem(project, item) {
        const entry = updateItem(item);
        const labels = { unknown: 'ยังยืนยันผลการส่งไม่ได้ (ข้อมูลเดิมหรือบริการส่งหลายช่องทาง)', pending: 'รอส่งอีเมล', sending: 'กำลังส่ง — หากค้างให้ตรวจผู้รับก่อน ไม่กดส่งซ้ำ', sent: 'บริการอีเมลรับข้อความแล้ว (ตรวจผู้รับเพื่อยืนยันว่าถึงจริง)', simulated: 'โหมดทดสอบ: ไม่ได้ส่งอีเมลออกจริง', failed: 'ส่งอีเมลไม่สำเร็จ', skipped: 'ไม่ได้ส่งอีเมลรายการนี้' };
        const delivery = el('div', { class: `email-delivery ${item.email_status || 'unknown'}` },
            el('p', {}, `อีเมล: ${labels[item.email_status] || labels.unknown} · ลองส่ง ${item.email_attempts || 0}/3 ครั้ง`),
            item.email_error ? el('p', {}, item.email_error) : null);
        if (item.email_can_retry && project.client && project.status !== 'archived') delivery.append(button('ลองส่งอีเมลใหม่', async event => {
            const control = event.currentTarget;
            if (!await askConfirmation('ยืนยันส่งอีเมล', 'ส่งแจ้งเตือนรายการนี้ไปยังอีเมล Client ที่ผูกกับโครงการ? ตรวจผู้รับและการตั้งค่า SMTP ก่อนส่ง', 'ยืนยันส่ง')) {
                await manageProject(project);
                return;
            }
            control.disabled = true;
            try {
                const result = await api(`/api/admin/projects/${project.id}/updates/${item.id}/retry-email`, { method: 'POST' });
                await manageProject(project);
                flash(result.message, result.data.email_status === 'failed');
            } catch (problem) {
                await manageProject(project);
                flash(problem.message, true);
            }
        }, 'small'));
        entry.append(delivery);
        return entry;
    }

    function milestoneForm(project, item = null) {
        openForm(item ? 'อัปเดต Milestone' : 'เพิ่ม Milestone', [['title', 'ชื่อขั้นงาน'], ['description', 'รายละเอียดสิ่งที่จะส่งมอบ', 'textarea', null, false], ['due_date', 'กำหนดส่ง', 'date'], ['status', 'สถานะ', 'text', milestoneStatuses]], data => api(`/api/admin/projects/${project.id}/milestones${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify(data) }), item || { status: 'pending' }, () => manageProject(project));
    }

    function uploadForm(project) {
        const form = el('form');
        const fileField = field('file', 'เลือกไฟล์ PDF, JPG, PNG, DOCX, XLSX หรือ ZIP (ไม่เกิน 10 MB)', 'file');
        fileField.querySelector('input').setAttribute('accept', '.pdf,.jpg,.jpeg,.png,.docx,.xlsx,.zip');
        form.append(fileField, field('visibility', 'ผู้ที่มองเห็นไฟล์', 'text', [['client', 'ลูกค้าและทีมงาน'], ['internal', 'ทีมงานเท่านั้น']]));
        const error = el('p', { class: 'form-error', role: 'alert' });
        const save = el('button', { type: 'submit', class: 'primary' }, 'อัปโหลดไฟล์');
        form.append(error, el('div', { class: 'actions' }, button('ยกเลิก', () => dialog.close()), save));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            save.disabled = true;
            error.textContent = '';
            try {
                await api(`/api/admin/projects/${project.id}/attachments`, { method: 'POST', body: new FormData(form) });
                dialog.close();
                flash('อัปโหลดไฟล์เรียบร้อย');
                await manageProject(project);
            } catch (problem) {
                error.textContent = problem.message;
            } finally {
                save.disabled = false;
            }
        });
        showDialog(el('header', {}, el('h2', {}, 'แนบไฟล์งาน'), button('ปิด ×', () => dialog.close(), 'small')), form);
    }

    async function deleteAttachment(project, attachment) {
        if (!await askConfirmation('ยืนยันการลบไฟล์', `ยืนยันการลบไฟล์ “${attachment.original_name}”?`, 'ยืนยันการลบไฟล์')) {
            await manageProject(project);
            return;
        }
        try {
            await api(`/api/admin/projects/${project.id}/attachments/${attachment.id}`, { method: 'DELETE' });
            flash('ลบไฟล์เรียบร้อย');
            await manageProject(project);
        } catch (problem) {
            flash(problem.message, true);
        }
    }

    function showClientProject(project) {
        const milestones = project.milestones.length ? el('ol', { class: 'timeline' }, project.milestones.map(item => milestoneItem(project, item, false))) : el('p', { class: 'empty' }, 'ยังไม่มี Milestone');
        const files = project.attachments.length ? el('ul', { class: 'file-list' }, project.attachments.map(item => fileItem(project, item, false))) : el('p', { class: 'empty' }, 'ยังไม่มีไฟล์ที่ส่งให้ลูกค้า');
        const updates = project.updates.length ? el('ol', { class: 'update-list' }, project.updates.map(updateItem)) : el('p', { class: 'empty' }, 'ยังไม่มีอัปเดตใหม่');
        showDialog(
            el('header', {}, el('div', {}, el('p', { class: 'eyebrow' }, 'CLIENT PROJECT'), el('h2', {}, project.project_name)), button('ปิด ×', () => dialog.close(), 'small')),
            el('div', { class: 'project-dialog-summary' }, badge(project.status), progressMeter(project.progress_percent)),
            el('div', { class: 'project-detail-grid' }, el('section', { class: 'detail-section' }, el('h3', {}, 'Milestone'), milestones), el('section', { class: 'detail-section' }, el('h3', {}, 'ไฟล์ส่งมอบ'), files)),
            el('section', { class: 'detail-section' }, el('h3', {}, 'อัปเดตล่าสุด'), updates)
        );
    }

    function milestoneItem(project, item, editable) {
        return el('li', { class: 'timeline-item' }, el('span', { class: `timeline-dot ${item.status}` }), el('div', { class: 'timeline-heading' }, el('h3', {}, item.title), badge(item.status)), el('p', {}, item.description || 'ไม่มีรายละเอียดเพิ่มเติม'), el('span', { class: 'caption' }, `กำหนด ${date(item.due_date)}`), editable ? el('div', { class: 'actions' }, button('แก้ไข', () => milestoneForm(project, item), 'small')) : null);
    }

    function fileItem(project, item, editable) {
        return el('li', {}, el('div', {}, el('strong', {}, item.original_name), el('span', {}, `${formatBytes(item.size_bytes)} · ${item.visibility === 'internal' ? 'ทีมงานเท่านั้น' : 'ลูกค้าเห็นได้'}`)), el('div', { class: 'actions' }, el('a', { class: 'button small', href: item.download_url }, 'ดาวน์โหลด'), editable ? button('ลบ', () => deleteAttachment(project, item), 'small danger') : null));
    }

    function updateItem(item) {
        return el('li', {}, el('div', { class: 'update-dot', 'aria-hidden': 'true' }), el('div', {}, el('div', { class: 'timeline-heading' }, el('strong', {}, item.title), item.progress_percent !== null ? badge(`${item.progress_percent}%`, 'in_progress') : null), item.body ? el('p', {}, item.body) : null, el('span', { class: 'caption' }, `${dateTime(item.created_at)} · ${item.user?.name || 'ระบบ'}${item.visible_to_client ? '' : ' · ภายในทีม'}`)));
    }

    function progressMeter(value = 0) {
        const percent = Math.max(0, Math.min(100, Number(value) || 0));
        return el('div', { class: 'progress-block' }, el('div', {}, el('span', {}, 'ความคืบหน้า'), el('strong', {}, `${percent}%`)), el('div', { class: 'progress-track', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(percent) }, el('span', { style: `width:${percent}%` })));
    }

    function fact(label, value) {
        return el('div', {}, el('dt', {}, label), el('dd', {}, value));
    }

    function formatBytes(bytes) {
        const value = Number(bytes) || 0;
        if (value < 1024) return `${value} B`;
        if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
        return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }

    function date(value) {
        if (!value) return 'ยังไม่กำหนด';
        return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium' }).format(new Date(value));
    }

    function dateTime(value) {
        if (!value) return 'ยังไม่มีข้อมูล';
        return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    }

    (async function boot() {
        try {
            user = (await api('/api/auth/me')).user;
            if (!user) {
                login();
                return;
            }
            active = user.role === 'client' ? 'projects' : 'overview';
            user.must_change_password ? passwordGate() : renderDashboard();
        } catch {
            login();
        }
    })();
});
