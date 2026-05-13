document.addEventListener('DOMContentLoaded', () => {
    initLegacyPageShell();
    initClock();
    initLoginValidation();
    initDashboard();
    initLegacyPanels();
});

function initLegacyPageShell() {
    if (document.querySelector('.app-shell')) {
        return;
    }

    const body = document.body;
    const legacyNav = body.querySelector('.image-container, .ico-container');
    const titleNode = body.querySelector('h1, h2');

    if (!legacyNav && !titleNode) {
        return;
    }

    const main = document.createElement('main');
    main.className = 'app-shell';

    const titleText = (titleNode?.textContent || document.title || 'Workspace').trim();
    const mark = titleText
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] || '')
        .join('')
        .toUpperCase() || 'AP';

    const links = [];
    if (legacyNav) {
        legacyNav.querySelectorAll('a').forEach((anchor) => {
            const href = anchor.getAttribute('href') || '#';
            const imgAlt = anchor.querySelector('img')?.getAttribute('alt') || '';
            const label = labelFromHref(href, imgAlt);
            if (!links.some((item) => item.href === href)) {
                links.push({ href, label });
            }
        });
    }

    const header = document.createElement('header');


    const hero = document.createElement('section');


    const panel = document.createElement('section');
    panel.className = 'content-panel legacy-panel';

    const nodesToMove = Array.from(body.childNodes).filter((node) => {
        if (node === main) {
            return false;
        }
        if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SCRIPT') {
            return false;
        }
        return true;
    });

    nodesToMove.forEach((node) => {
        if (node === legacyNav) {
            return;
        }
        panel.appendChild(node);
    });

    if (titleNode && titleNode.parentElement === panel) {
        titleNode.classList.add('legacy-heading');
    }

    main.appendChild(header);
    main.appendChild(hero);
    main.appendChild(panel);

    if (legacyNav) {
        legacyNav.remove();
    }

    body.prepend(main);
}

function labelFromHref(href, alt) {
    const normalized = (alt || href)
        .replace(/\.php$/i, '')
        .replace(/[_-]+/g, ' ')
        .replace(/\//g, ' ')
        .trim();

    if (/welcome/i.test(normalized) || /home/i.test(normalized)) {
        return 'Home';
    }
    if (/logout/i.test(normalized)) {
        return 'Logout';
    }
    if (/cpanel|control panel/i.test(normalized)) {
        return 'Cpanel';
    }
    if (/report/i.test(normalized)) {
        return 'Reports';
    }
    if (/evaluation/i.test(normalized)) {
        return 'Evaluation';
    }
    if (/night/i.test(normalized)) {
        return 'Night Shift';
    }
    if (/overtime|otre/i.test(normalized)) {
        return 'Overtime';
    }
    return normalized.replace(/\b\w/g, (char) => char.toUpperCase()) || 'Open';
}

function initClock() {
    const clockNodes = document.querySelectorAll('[data-live-clock]');
    if (!clockNodes.length) return;

    const formatter = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    const render = () => {
        const now = formatter.format(new Date());
        clockNodes.forEach((node) => {
            node.textContent = now;
        });
    };

    render();
    window.setInterval(render, 60000);
}

function initLoginValidation() {
    const form = document.querySelector('[data-login-form]');
    if (!form) return;

    const username = form.querySelector('input[name="username"]');
    const password = form.querySelector('input[name="password"]');
    const banner = document.querySelector('[data-login-error]');

    form.addEventListener('submit', (event) => {
        const invalidFields = [username, password].filter((field) => !field.value.trim());
        [username, password].forEach((field) => field.classList.remove('input-error'));
        if (!invalidFields.length) return;

        invalidFields.forEach((field) => field.classList.add('input-error'));
        if (banner) {
            banner.textContent = 'Username and password are both required.';
            banner.classList.remove('is-hidden');
        }
        event.preventDefault();
    });
}

function initDashboard() {
    const dashboard = document.querySelector('[data-dashboard]');
    if (!dashboard) return;

    const endpoint = dashboard.getAttribute('data-dashboard-endpoint');
    const statsContainer = dashboard.querySelector('[data-summary-cards]');
    const modulesContainer = dashboard.querySelector('[data-module-cards]');
    const statusChip = dashboard.querySelector('[data-sadv-status]');
    const quarterNode = dashboard.querySelector('[data-quarter-label]');
    const filterInput = dashboard.querySelector('[data-module-filter]');
    const emptyState = dashboard.querySelector('[data-module-empty]');

    const applyFilter = () => {
        if (!modulesContainer || !filterInput) return;
        const needle = filterInput.value.trim().toLowerCase();
        const cards = modulesContainer.querySelectorAll('[data-module-card]');
        let visibleCount = 0;

        cards.forEach((card) => {
            const haystack = `${card.getAttribute('data-title')} ${card.getAttribute('data-tags')}`.toLowerCase();
            const matches = !needle || haystack.includes(needle);
            card.classList.toggle('is-hidden', !matches);
            if (matches) visibleCount += 1;
        });

        if (emptyState) {
            emptyState.classList.toggle('is-hidden', visibleCount !== 0);
        }
    };

    if (filterInput) filterInput.addEventListener('input', applyFilter);
    if (!endpoint) {
        applyFilter();
        return;
    }

    fetch(endpoint, { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error(`Dashboard request failed with ${response.status}`);
            return response.json();
        })
        .then((payload) => {
            if (statsContainer && Array.isArray(payload.summaryCards)) {
                statsContainer.innerHTML = payload.summaryCards.map((card) => `
                    <article class="stat-card">
                        <strong>${escapeHtml(String(card.value ?? 0))}</strong>
                        <p>${escapeHtml(card.label ?? '')}</p>
                    </article>
                `).join('');
            }

            if (modulesContainer && Array.isArray(payload.modules)) {
                modulesContainer.innerHTML = payload.modules.map(renderModuleCard).join('');
            }

            if (statusChip) {
                const isOpen = Boolean(payload.status?.salaryAdvanceWindow);
                statusChip.textContent = isOpen ? 'Salary advance window is open' : 'Salary advance window is closed';
                statusChip.classList.toggle('is-closed', !isOpen);
            }

            if (quarterNode) {
                const quarter = payload.status?.currentQuarter;
                const year = payload.status?.currentYear;
                quarterNode.textContent = quarter ? `Current evaluation cycle: Q${quarter} ${year}` : `Current evaluation cycle: ${year}`;
            }

            applyFilter();
        })
        .catch((error) => {
            console.error(error);
            if (statusChip) {
                statusChip.textContent = 'Live status unavailable';
                statusChip.classList.add('is-closed');
            }
            applyFilter();
        });
}

function renderModuleCard(module) {
    const links = Array.isArray(module.links) ? module.links : [];
    const tags = links.map((link) => link.label).join(' ');

    return `
        <article class="module-card" data-module-card data-accent="${escapeHtml(module.accent || 'ruby')}" data-title="${escapeHtml(module.title || '')}" data-tags="${escapeHtml(tags)}">
            <div class="module-head">
                <div>
                    <h3>${escapeHtml(module.title || '')}</h3>
                    <p>${escapeHtml(module.description || '')}</p>
                </div>
                <div class="module-icon">${escapeHtml(module.icon || 'AP')}</div>
            </div>
            <div class="module-links">
                <a class="btn-primary" href="${escapeHtml(module.href || '#')}">Open</a>
                ${links.map((link) => `<a class="pill-link" href="${escapeHtml(link.href || '#')}">${escapeHtml(link.label || '')}</a>`).join('')}
            </div>
        </article>
    `;
}

function initLegacyPanels() {
    const panels = document.querySelectorAll('.legacy-panel');

    panels.forEach((panel) => {
        panel.querySelectorAll('table').forEach((table) => {
            if (table.parentElement?.classList.contains('table-scroll')) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'table-scroll';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });

        panel.querySelectorAll('.form-group').forEach((group) => group.classList.add('form-row'));
        panel.querySelectorAll('button, input[type="submit"]').forEach((button) => button.classList.add('legacy-action'));
        panel.querySelectorAll('select').forEach((select) => select.classList.add('legacy-select'));
        panel.querySelectorAll('input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"])').forEach((input) => input.classList.add('legacy-input'));
        panel.querySelectorAll('.radio-group').forEach((group) => group.classList.add('score-grid'));
        panel.querySelectorAll('h1, h2, h3').forEach((heading) => {
            if (!heading.closest('.hero-panel')) heading.classList.add('legacy-heading');
        });
        panel.querySelectorAll('p').forEach((paragraph) => {
            const text = paragraph.textContent.trim().toLowerCase();
            if (text.includes('successfully') || text.startsWith('error:') || text.includes('access denied')) {
                paragraph.classList.add('message-card');
            }
        });
    });
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
