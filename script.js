// Global variables
let currentTestimonialIndex = 0;
let map = null;
let alumniMarkers = [];
let selectedLocation = null;
let currentCategorySection = null;

if (typeof alumniData !== 'undefined') {
    window.alumniData = alumniData;
}

if (typeof placedAlumniData !== 'undefined') {
    window.placedAlumniData = placedAlumniData;
}

if (typeof higherStudiesAlumniData !== 'undefined') {
    window.higherStudiesAlumniData = higherStudiesAlumniData;
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOMContentLoaded ===');
    console.log('Initial data check:', {
        windowAlumniData: window.alumniData,
        hasData: !!window.alumniData,
        dataLength: window.alumniData ? window.alumniData.length : 0
    });
    
    initializeNavigation();
    // Only initialize mobile menu if elements exist
    if (document.getElementById('mobileMenuBtn') || document.getElementById('mobileMenu')) {
        initializeMobileMenu();
    }
    initializeMap();
    initializeSearchFilters();
    initializeAlumniData();

    if (window.location.hash) {
        const hash = window.location.hash.replace('#', '');
        navigateToSection(hash);
    }
});

function normalizeStatusValue(value) {
    if (!value) return '';
    return value
        .toString()
        .toLowerCase()
        .replace(/[^a-z\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function isWorkingStatus(statusValue) {
    const status = normalizeStatusValue(statusValue);
    if (!status) return false;

    const negativePatterns = [
        /\bnot\s+work/,
        /\bunemploy/,
        /\bno\s+work/,
        /\blooking\s+for\s+work/,
        /\bseeking\s+(a\s+)?job/,
        /\bjob\s+seeker/,
        /\bfresher\b/,
        /\bhomemaker\b/,
        /\bcareer\s+break\b/
    ];
    if (negativePatterns.some(pattern => pattern.test(status))) {
        return false;
    }

    const positivePatterns = [
        /\bwork(ing)?\b/,
        /\bemploy(ed|ment)?\b/,
        /\bjob\b/,
        /\bplaced\b/,
        /\bprofessional\b/,
        /\bfull[-\s]?time\b/,
        /\bpart[-\s]?time\b/
    ];

    return positivePatterns.some(pattern => pattern.test(status));
}

function isHigherStudiesStatus(statusValue) {
    const status = normalizeStatusValue(statusValue);
    if (!status) return false;

    const positivePatterns = [
        /\bstudy(ing)?\b/,
        /\bstudent\b/,
        /\bhigher\b/,
        /\bmasters?\b/,
        /\bmpa\b/,
        /\bmba\b/,
        /\bmphil\b/,
        /\bphd\b/,
        /\bpursu(ing|t)\b/,
        /\bpost\s*grad/,
        /\bpg\b/,
        /\bms\b/
    ];

    return positivePatterns.some(pattern => pattern.test(status));
}

// Navigation functionality
function initializeNavigation() {
    const navItems = document.querySelectorAll('.nav-item, .mobile-nav-item');
    const sections = ['home', 'dashboard', 'info', 'testimonials'];
    
    // Handle navigation clicks
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            const section = this.dataset.section;
            removeCategorySections();
            navigateToSection(section);
            
            // Update active states
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Close mobile menu if open
            const mobileMenu = document.getElementById('mobileMenu');
            if (mobileMenu.classList.contains('open')) {
                mobileMenu.classList.remove('open');
            }
        });
    });
    
    // Handle scroll-based navigation highlighting
    window.addEventListener('scroll', function() {
        const scrollPosition = window.scrollY + 100;
        
        for (const section of sections) {
            const element = document.getElementById(section);
            if (element) {
                const { offsetTop, offsetHeight } = element;
                if (scrollPosition >= offsetTop && scrollPosition < offsetTop + offsetHeight) {
                    updateActiveNavigation(section);
                    break;
                }
            } else if (section === 'home' && scrollPosition < 100) {
                updateActiveNavigation('home');
            }
        }
    });
}

function navigateToSection(section) {
    const element = document.getElementById(section);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        if (history.replaceState) {
            history.replaceState(null, '', `#${section}`);
        }
    } else if (section === 'home') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (history.replaceState) {
            history.replaceState(null, '', '#home');
        }
    }
}

function setHomeVisibility(isVisible) {
    const navigation = document.getElementById('navigation');
    if (navigation) {
        navigation.classList.remove('is-hidden');
        navigation.style.display = '';
    }
}

function removeCategorySections() {
    const portal = document.getElementById('categoryPortal');
    if (portal) {
        portal.innerHTML = '';
        portal.className = 'alumni-category-root';
        portal.removeAttribute('data-category');
    }
    setHomeVisibility(true);
    currentCategorySection = null;
}

function renderCategorySection(sectionId) {
    if (!window.alumniData || !Array.isArray(window.alumniData)) return;

    const portal = document.getElementById('categoryPortal');
    if (!portal) return;

    removeCategorySections();
    setHomeVisibility(false);

    const configMap = {
        'placed-alumni': {
            className: 'placed-section',
            title: '💼 Placements',
            subtitle: 'Celebrating alumni excelling in their professional journeys',
            data: window.placedAlumniData,
            filter: alumni => alumni.currentStatus === 'Working',
            summary: getPlacementSummary,
            searchPlaceholder: 'Search by company, role, or alumni...',
            groupLabel: 'Company',
            allLabel: 'All Alumni',
            groupIcon: 'fas fa-building',
            groupKey: alumni => alumni.company || 'Independent Consultant',
            groupFallback: 'Independent Consultant',
            groupMeta: members => summariseLocations(members),
            regionKey: alumni => extractCountry(alumni.location),
            regionLabel: 'All Regions',
            alumniLabelSingular: 'Alumni',
            alumniLabelPlural: 'Alumni'
        },
        'higher-studies': {
            className: 'studies-section',
            title: '🎓 Alumni Pursuing Higher Studies',
            subtitle: 'Highlighting alumni expanding their academic horizons',
            data: window.higherStudiesAlumniData,
            filter: alumni => alumni.currentStatus === 'Studying',
            summary: getStudiesSummary,
            searchPlaceholder: 'Search by institution, program, or alumni...',
            groupLabel: 'Institution',
            allLabel: 'All Alumni',
            groupIcon: 'fas fa-university',
            groupKey: alumni => alumni.education?.institution || alumni.education?.university || 'Institution Pending',
            groupFallback: 'Institution Pending',
            groupMeta: members => summariseLocations(members),
            regionKey: alumni => extractCountry(alumni.location),
            regionLabel: 'All Regions',
            alumniLabelSingular: 'Alumni',
            alumniLabelPlural: 'Alumni'
        }
    };

    const config = configMap[sectionId];
    if (!config) return;

    const dataset = Array.isArray(config.data) && config.data.length > 0
        ? config.data
        : window.alumniData.filter(config.filter);

    const filtered = dataset.filter(Boolean);
    const isFlashcardSection = sectionId === 'placed-alumni' || sectionId === 'higher-studies';
    const summaryData = isFlashcardSection
        ? null
        : config.summary(filtered, config);
    const grouped = isFlashcardSection
        ? []
        : getGroupedCollection(filtered, config);

    if (isFlashcardSection) {
        const emptyMessage = sectionId === 'placed-alumni'
            ? 'No placements to display yet. Check back soon!'
            : 'No alumni currently pursuing higher studies to display.';
        const emptyIcon = sectionId === 'placed-alumni'
            ? 'fas fa-briefcase'
            : 'fas fa-graduation-cap';
        const cardsMarkup = filtered.length
            ? `<div class="flashcard-grid">
                    ${filtered.map(alumni => renderCategoryCard(sectionId, alumni, config)).join('')}
               </div>`
            : `
                <div class="category-empty">
                    <i class="${emptyIcon}"></i>
                    <p>${emptyMessage}</p>
                </div>
              `;

        portal.innerHTML = `
            <div class="container category-shell placement-flashcards" data-category="${sectionId}">
                <div class="section-header category-header">
                    <h2 class="section-title">${escapeHtml(config.title)}</h2>
                    <p class="section-subtitle">${escapeHtml(config.subtitle)}</p>
                </div>
                ${cardsMarkup}
            </div>
        `;
    } else {
        portal.innerHTML = `
            <div class="container category-shell" data-category="${sectionId}">
                <div class="section-header category-header">
                    <h2 class="section-title">${escapeHtml(config.title)}</h2>
                    <p class="section-subtitle">${escapeHtml(config.subtitle)}</p>
                </div>

                <div class="category-summary">
                    ${summaryData.cards.join('')}
                </div>

                <div class="category-controls">
                    <div class="category-search">
                        <i class="fas fa-search"></i>
                        <input class="category-search-input" type="text" placeholder="${escapeHtml(config.searchPlaceholder)}">
                    </div>
                    ${summaryData.filters?.length > 1 ? `<div class="category-filters">${renderFilterChips(summaryData.filters)}</div>` : ''}
                </div>

                <div class="category-tabs" role="tablist">
                    <button class="category-tab is-active" data-view="grouped">By ${escapeHtml(config.groupLabel)}</button>
                    <button class="category-tab" data-view="all">${escapeHtml(config.allLabel)}</button>
                </div>

                <div class="category-view category-view--grouped" data-view="grouped">
                    ${renderGroupedList(grouped, config, sectionId)}
                </div>
                <div class="category-view category-view--all" data-view="all" hidden>
                    ${renderAllCards(filtered, sectionId, config)}
                </div>
            </div>
        `;
    }

    portal.className = `alumni-category-root is-visible ${config.className}`;
    portal.setAttribute('data-category', sectionId);
    currentCategorySection = sectionId;

    attachCategoryInteractions(portal, config);
    updateActiveNavigation(sectionId);
    const navigation = document.getElementById('navigation');
    if (navigation) {
        navigation.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    if (history.replaceState) {
        history.replaceState(null, '', `#${sectionId}`);
    }
}

function renderGroupedList(groups, config, sectionId) {
    if (!groups.length) {
        return `
            <div class="category-empty">
                <i class="${sectionId === 'placed-alumni' ? 'fas fa-briefcase' : 'fas fa-graduation-cap'}"></i>
                <p>${sectionId === 'placed-alumni' ? 'No placements to display yet. Check back soon!' : 'No alumni currently pursuing higher studies to display.'}</p>
            </div>
        `;
    }

    return `
        <div class="category-group-list">
            ${groups.map((group, index) => renderGroupCard(sectionId, group, index, config)).join('')}
        </div>
    `;
}

function renderAllCards(alumni, sectionId, config) {
    if (!alumni.length) {
        return `
            <div class="category-empty">
                <i class="${sectionId === 'placed-alumni' ? 'fas fa-briefcase' : 'fas fa-graduation-cap'}"></i>
                <p>${sectionId === 'placed-alumni' ? 'No placements to display yet. Check back soon!' : 'No alumni currently pursuing higher studies to display.'}</p>
            </div>
        `;
    }

    return `
        <div class="alumni-grid category-grid">
            ${alumni.map(item => renderCategoryCard(sectionId, item, config)).join('')}
        </div>
    `;
}

function renderGroupCard(sectionId, group, index, config) {
    const count = group.members.length;
    const label = count === 1 ? config.alumniLabelSingular : config.alumniLabelPlural;
    const groupTokens = [
        group.label,
        group.meta,
        group.region,
        ...group.members.map(member => [
            member.name,
            member.role,
            member.company,
            member.education?.degree,
            member.education?.institution,
            member.education?.university,
            member.location
        ].filter(Boolean).join(' '))
    ].join(' ').toLowerCase();

    return `
        <details class="category-group-card" data-search="${escapeHtml(groupTokens)}" data-total="${count}" data-region="${escapeHtml(group.regionSlug)}" ${index === 0 ? 'open' : ''}>
            <summary>
                <div class="category-group-header">
                    <div class="category-group-icon">
                        <i class="${config.groupIcon}"></i>
                    </div>
                    <div class="category-group-info">
                        <span class="category-group-title">${escapeHtml(group.label)}</span>
                        ${group.meta ? `<span class="category-group-meta">${escapeHtml(group.meta)}</span>` : ''}
                        ${group.region ? `<span class="category-group-region">${escapeHtml(group.region)}</span>` : ''}
                    </div>
                </div>
                <span class="category-group-count">
                    <strong>${count}</strong>
                    <span>${label}</span>
                </span>
            </summary>
            <div class="category-group-members">
                ${group.members.map(member => renderGroupMember(sectionId, member, config)).join('')}
            </div>
        </details>
    `;
}

function renderGroupMember(sectionId, alumni, config) {
    const regionLabel = normalizeRegion(config.regionKey ? config.regionKey(alumni) : config.regionLabel, config.regionLabel);
    const locationText = alumni.location ? prettifyLocation(alumni.location) : regionLabel;
    const tokens = [
        alumni.name,
        alumni.role,
        alumni.company,
        alumni.education?.degree,
        alumni.education?.institution,
        alumni.education?.university,
        alumni.location,
        alumni.statusDetails,
        regionLabel
    ].filter(Boolean).join(' ').toLowerCase();

    const name = escapeHtml(alumni.name);
    const linkedin = escapeHtml(alumni.linkedin || '');

    const hasHigherEducation = Boolean(alumni.education?.degree || alumni.education?.institution || alumni.education?.university);
    const hasEmployment = Boolean(alumni.role || alumni.company);

    const primaryLine = sectionId === 'placed-alumni'
        ? `${escapeHtml(alumni.role || 'Role not specified')}${alumni.company ? ` • ${escapeHtml(alumni.company)}` : ''}`
        : `${escapeHtml(alumni.education?.degree || 'Program not specified')} • ${escapeHtml(alumni.education?.institution || alumni.education?.university || 'Institution not specified')}`;

    const secondaryLine = (() => {
        if (sectionId === 'placed-alumni' && hasHigherEducation) {
            const degree = escapeHtml(alumni.education?.degree || '');
            const institution = escapeHtml(alumni.education?.institution || alumni.education?.university || '');
            if (degree || institution) {
                return `Higher Studies: ${[degree, institution].filter(Boolean).join(' • ')}`;
            }
        }
        if (sectionId === 'higher-studies' && hasEmployment) {
            const role = escapeHtml(alumni.role || '');
            const company = escapeHtml(alumni.company || '');
            if (role || company) {
                return `Working: ${[role, company].filter(Boolean).join(' • ')}`;
            }
        }
        return '';
    })();

    return `
        <div class="category-member" data-search="${escapeHtml(tokens)}" data-region="${escapeHtml(slugify(regionLabel))}">
            <div class="category-member-info">
                <span class="category-member-name">${name}</span>
                <span class="category-member-role">${primaryLine}</span>
                ${secondaryLine ? `<span class="category-member-meta category-member-extra">${secondaryLine}</span>` : ''}
                ${locationText ? `<span class="category-member-meta"><i class="fas fa-map-marker-alt"></i>${escapeHtml(locationText)}</span>` : ''}
            </div>
            ${linkedin ? `
            <a class="category-linkedin" href="${linkedin}" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-linkedin"></i> Connect
            </a>` : ''}
        </div>
    `;
}

function getGroupedCollection(data, config) {
    const groups = new Map();

    data.forEach(alumni => {
        const key = (config.groupKey(alumni) || config.groupFallback).trim();
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key).push(alumni);
    });

    return Array.from(groups.entries()).map(([label, members]) => ({
        label,
        meta: config.groupMeta ? config.groupMeta(members) : '',
        region: normalizeRegion(config.regionKey ? config.regionKey(members[0]) : config.regionLabel, config.regionLabel),
        regionSlug: slugify(normalizeRegion(config.regionKey ? config.regionKey(members[0]) : config.regionLabel, config.regionLabel)),
        members
    })).sort((a, b) => b.members.length - a.members.length);
}

function getPlacementSummary(data, config) {
    const companyCounts = getCounts(data, item => item.company || 'Independent Consultant');
    const roleCounts = getCounts(data, item => item.role || 'Role not specified');
    const locationCounts = getCounts(data, item => extractLocation(item.location));

    const totalCompanies = Object.keys(companyCounts).length;
    const topCompany = getTopEntry(companyCounts, 'Not available');
    const topRole = getTopEntry(roleCounts, 'Not available');
    const topLocation = getTopEntry(locationCounts, 'Multiple locations');

    const cards = [
        cardTemplate('fas fa-users', 'Total Placements', data.length, `${totalCompanies} companies`),
        cardTemplate('fas fa-building', 'Top Recruiter', topCompany.label, `${topCompany.count} placement${topCompany.count === 1 ? '' : 's'}`),
        cardTemplate('fas fa-briefcase', 'Popular Role', topRole.label, `${topRole.count} alumni`),
        cardTemplate('fas fa-map-marker-alt', 'Key Location', topLocation.label, `${topLocation.count} alumni`)
    ];

    return { cards, filters: buildRegionFilters(data, config) };
}

function getStudiesSummary(data, config) {
    const institutionCounts = getCounts(data, item => item.education?.institution || item.education?.university || 'Institution Pending');
    const programCounts = getCounts(data, item => item.education?.degree || 'Program Pending');
    const countryCounts = getCounts(data, item => extractCountry(item.location));

    const totalInstitutions = Object.keys(institutionCounts).length;
    const topInstitution = getTopEntry(institutionCounts, 'Not available');
    const topProgram = getTopEntry(programCounts, 'Not available');
    const totalCountries = Object.keys(countryCounts).length;

    const cards = [
        cardTemplate('fas fa-graduation-cap', 'Total Scholars', data.length, `${totalInstitutions} institutions`),
        cardTemplate('fas fa-university', 'Top Institution', topInstitution.label, `${topInstitution.count} enrolment${topInstitution.count === 1 ? '' : 's'}`),
        cardTemplate('fas fa-book', 'Popular Program', topProgram.label, `${topProgram.count} alumni`),
        cardTemplate('fas fa-globe', 'Global Footprint', `${totalCountries} ${totalCountries === 1 ? 'Country' : 'Countries'}`, 'Diverse presence')
    ];

    return { cards, filters: buildRegionFilters(data, config) };
}

function attachCategoryInteractions(section, config) {
    const searchInput = section.querySelector('.category-search-input');
    const tabs = Array.from(section.querySelectorAll('.category-tab'));
    const views = Array.from(section.querySelectorAll('.category-view'));
    const filterButtons = Array.from(section.querySelectorAll('.category-filter'));
    let activeRegion = 'all';

    function setActiveView(target) {
        views.forEach(view => {
            if (view.dataset.view === target) {
                view.hidden = false;
                view.classList.add('is-active');
            } else {
                view.hidden = true;
                view.classList.remove('is-active');
            }
        });
        applySearch(searchInput ? searchInput.value.toLowerCase() : '');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            if (tab.classList.contains('is-active')) return;
            tabs.forEach(btn => btn.classList.remove('is-active'));
            tab.classList.add('is-active');
            setActiveView(tab.dataset.view);
        });
    });

    if (filterButtons.length) {
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.classList.contains('is-active')) return;
                filterButtons.forEach(btn => btn.classList.remove('is-active'));
                button.classList.add('is-active');
                activeRegion = button.dataset.filter || 'all';
                applySearch(searchInput ? searchInput.value.toLowerCase() : '');
            });
        });
    }

    function applySearch(term) {
        const cards = Array.from(section.querySelectorAll('.category-card'));
        cards.forEach(card => {
            const haystack = (card.dataset.search || '').toLowerCase();
            const region = (card.dataset.region || 'all').toLowerCase();
            const matchesRegion = activeRegion === 'all' || region === activeRegion;
            card.style.display = (!term || haystack.includes(term)) && matchesRegion ? '' : 'none';
        });

        const groupCards = Array.from(section.querySelectorAll('.category-group-card'));
        groupCards.forEach(card => {
            const region = (card.dataset.region || 'all').toLowerCase();
            const matchesRegion = activeRegion === 'all' || region === activeRegion;
            if (!matchesRegion) {
                card.style.display = 'none';
                return;
            }

            const members = Array.from(card.querySelectorAll('.category-member'));
            let visibleCount = 0;
            members.forEach(member => {
                const haystack = (member.dataset.search || '').toLowerCase();
                const memberRegion = (member.dataset.region || region).toLowerCase();
                const matchesMemberRegion = activeRegion === 'all' || memberRegion === activeRegion;
                const isMatch = (!term || haystack.includes(term)) && matchesMemberRegion;
                member.style.display = isMatch ? '' : 'none';
                if (isMatch) visibleCount++;
            });

            const badge = card.querySelector('.category-group-count strong');
            const label = card.querySelector('.category-group-count span');
            if (badge) badge.textContent = visibleCount;
            if (label) label.textContent = ` ${visibleCount === 1 ? config.alumniLabelSingular : config.alumniLabelPlural}`;

            card.style.display = visibleCount > 0 ? '' : 'none';
            if (visibleCount === 0 && card.hasAttribute('open')) {
                card.removeAttribute('open');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => applySearch(searchInput.value.toLowerCase()));
    }

    applySearch('');
}

function cardTemplate(icon, label, primary, secondary) {
    return `
        <div class="summary-card">
            <div class="summary-icon">
                <i class="${icon}"></i>
            </div>
            <div class="summary-content">
                <p>${escapeHtml(label)}</p>
                <h3>${escapeHtml(primary !== undefined && primary !== null ? String(primary) : '—')}</h3>
                ${secondary ? `<span class="summary-sub">${escapeHtml(String(secondary))}</span>` : ''}
            </div>
        </div>
    `;
}

function summariseLocations(members) {
    const locations = members
        .map(member => (member.location || '').trim())
        .filter(Boolean);

    if (!locations.length) {
        return '';
    }

    const unique = Array.from(new Set(locations.map(prettifyLocation)));
    if (unique.length === 1) {
        return unique[0];
    }
    if (unique.length === 2) {
        return `${unique[0]} • ${unique[1]}`;
    }
    return `${unique[0]} • ${unique[1]} +${unique.length - 2} more`;
}

function prettifyLocation(value) {
    return value.split(',').map(part => toTitleCase(part.trim())).filter(Boolean).join(', ');
}

function toTitleCase(value) {
    return value.split(/\s+/).map(word => {
        const lower = word.toLowerCase();
        return lower.charAt(0).toUpperCase() + lower.slice(1);
    }).join(' ');
}

function normalizeRegion(value, fallback = 'All Regions') {
    if (!value || String(value).toLowerCase() === 'unknown') return fallback;
    return toTitleCase(value);
}

function slugify(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'all';
}

function shouldDisplayStatus(status, alumni) {
    if (!status) return false;
    const normalized = status.toLowerCase();
    const comparisons = [
        alumni.role,
        alumni.company,
        alumni.location
    ].filter(Boolean).map(value => String(value).toLowerCase());

    return !comparisons.some(value => normalized.includes(value));
}

function buildRegionFilters(data, config) {
    if (!config.regionKey) {
        return [{ label: config.regionLabel || 'All Regions', value: 'all', count: data.length, active: true }];
    }

    const regionLabel = config.regionLabel || 'All Regions';
    const counts = getCounts(data, item => normalizeRegion(config.regionKey(item), regionLabel));
    const entries = Object.entries(counts).filter(([label]) => label && label !== regionLabel && label !== 'Unknown');
    entries.sort((a, b) => b[1] - a[1]);

    const filters = [{
        label: regionLabel,
        value: 'all',
        count: data.length,
        active: true
    }];

    entries.slice(0, 5).forEach(([label, count]) => {
        filters.push({
            label,
            value: slugify(label),
            count
        });
    });

    return filters;
}

function renderFilterChips(filters) {
    if (!filters || !filters.length) return '';
    return filters.map((filter, index) => `
        <button class="category-filter ${filter.active || index === 0 ? 'is-active' : ''}" data-filter="${filter.value}">
            ${escapeHtml(filter.label)}
            ${filter.count !== undefined ? `<span class="filter-count">${filter.count}</span>` : ''}
        </button>
    `).join('');
}

function renderCategoryCard(sectionId, alumni, config) {
    const regionLabel = normalizeRegion(config.regionKey ? config.regionKey(alumni) : config.regionLabel, config.regionLabel);
    const locationText = alumni.location ? prettifyLocation(alumni.location) : regionLabel;
    const tokens = [
        alumni.name,
        alumni.program,
        alumni.year,
        alumni.role,
        alumni.company,
        alumni.education?.degree,
        alumni.education?.institution,
        alumni.education?.university,
        alumni.location,
        alumni.statusDetails,
        regionLabel
    ].filter(Boolean).join(' ').toLowerCase();

    const name = escapeHtml(alumni.name);
    const program = escapeHtml(alumni.program || '');
    const year = alumni.year ? escapeHtml(String(alumni.year)) : '';
    const rawStatus = alumni.statusDetails || '';
    const statusDetails = shouldDisplayStatus(rawStatus, alumni) ? escapeHtml(rawStatus) : '';
    const linkedin = escapeHtml(alumni.linkedin || '');

    const infoBlock = sectionId === 'placed-alumni'
        ? placementInfo(alumni)
        : studiesInfo(alumni);

    return `
        <div class="alumni-card category-card" data-search="${escapeHtml(tokens)}" data-region="${escapeHtml(slugify(regionLabel))}">
            <div class="alumni-card-header">
                <div class="alumni-card-title">${name}</div>
                <div class="alumni-card-subtitle">${program}${year ? ` • ${year}` : ''}</div>
                <span class="alumni-status-badge ${sectionId === 'placed-alumni' ? 'working' : 'studying'}">
                    ${sectionId === 'placed-alumni' ? 'Working' : 'Studying'}
                </span>
            </div>
            <div class="alumni-card-content">
                ${statusDetails ? `<p class="alumni-info">${statusDetails}</p>` : ''}
                ${infoBlock}
                ${locationText ? `
                    <div class="alumni-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${escapeHtml(locationText)}</span>
                    </div>` : ''}
                ${linkedin ? `
                    <div class="alumni-linkedin">
                        <a href="${linkedin}" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-linkedin"></i>
                            Connect on LinkedIn →
                        </a>
                    </div>` : ''}
            </div>
        </div>
    `;
}

function placementInfo(alumni) {
    const role = escapeHtml(alumni.role || 'Role not specified');
    const company = escapeHtml(alumni.company || 'Independent Consultant');
    const degree = escapeHtml(alumni.education?.degree || '');
    const institution = escapeHtml(alumni.education?.institution || alumni.education?.university || '');
    const hasHigherEducation = Boolean(degree || institution);

    return `
        <div class="category-card-meta">
            <div>
                <span class="meta-label">Role</span>
                <span class="meta-value">${role}</span>
            </div>
            <div>
                <span class="meta-label">Company</span>
                <span class="meta-value">${company}</span>
            </div>
        </div>
        ${hasHigherEducation ? `
        <div class="category-card-meta category-card-meta--secondary">
            <div>
                <span class="meta-label">Higher Studies</span>
                <span class="meta-value">${[degree, institution].filter(Boolean).join(' • ') || 'Details not specified'}</span>
            </div>
        </div>
        ` : ''}
    `;
}

function studiesInfo(alumni) {
    const degree = escapeHtml(alumni.education?.degree || 'Program not specified');
    const institution = escapeHtml(alumni.education?.institution || alumni.education?.university || 'Institution not specified');
    const role = escapeHtml(alumni.role || '');
    const company = escapeHtml(alumni.company || '');
    const hasEmployment = Boolean(role || company);

    return `
        <div class="category-card-meta">
            <div>
                <span class="meta-label">Program</span>
                <span class="meta-value">${degree}</span>
            </div>
            <div>
                <span class="meta-label">Institution</span>
                <span class="meta-value">${institution}</span>
            </div>
        </div>
        ${hasEmployment ? `
        <div class="category-card-meta category-card-meta--secondary">
            <div>
                <span class="meta-label">Working</span>
                <span class="meta-value">${[role, company].filter(Boolean).join(' • ') || 'Role not specified'}</span>
            </div>
        </div>
        ` : ''}
    `;
}

function getCounts(data, selector) {
    return data.reduce((acc, item) => {
        const key = selector(item);
        if (!key) return acc;
        const value = String(key).trim();
        if (!value) return acc;
        acc[value] = (acc[value] || 0) + 1;
        return acc;
    }, {});
}

function getTopEntry(counts, fallbackLabel) {
    let topLabel = fallbackLabel;
    let topCount = 0;
    Object.entries(counts).forEach(([label, count]) => {
        if (count > topCount) {
            topLabel = label;
            topCount = count;
        }
    });
    return { label: topLabel, count: topCount };
}

function extractLocation(location) {
    if (!location) return 'Multiple locations';
    return location.split(',')[0].trim();
}

function extractCountry(location) {
    if (!location) return 'Unknown';
    const parts = location.split(',');
    return parts[parts.length - 1].trim();
}

function escapeHtml(value) {
    if (typeof value !== 'string') return value || '';
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function updateActiveNavigation(activeSection) {
    const navItems = document.querySelectorAll('.nav-item, .mobile-nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
        if (item.dataset.section === activeSection) {
            item.classList.add('active');
        }
    });
}

// Mobile menu functionality
function initializeMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    
    // Check if elements exist before adding event listeners
    if (!mobileMenuBtn || !mobileMenu) {
        return; // Mobile menu elements not present on this page
    }
    
    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.add('open');
    });
    
    if (mobileMenuClose) {
        mobileMenuClose.addEventListener('click', function() {
            mobileMenu.classList.remove('open');
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileMenuBtn && 
            !mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
            mobileMenu.classList.remove('open');
        }
    });
}

// Map functionality
function initializeMap() {
    // Check if map element exists
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.warn('Map element not found, skipping map initialization');
        return;
    }
    
    // Check if map is already initialized
    if (map !== null) {
        console.log('Map already initialized');
        return;
    }
    
    try {
    // Initialize Leaflet map
    map = L.map('map').setView([20.5937, 78.9629], 5);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Fix Leaflet icon paths
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
        iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
    });
        
        console.log('Map initialized successfully');
        
        // Invalidate map size after a short delay to ensure proper rendering
        setTimeout(() => {
            if (map) {
                map.invalidateSize();
            }
        }, 100);
    } catch (error) {
        console.error('Error initializing map:', error);
    }
}

function createPurpleIcon(count) {
    const svgIcon = `
        <svg width="30" height="40" xmlns="http://www.w3.org/2000/svg">
            <path d="M15,1 C8.373,1 3,6.373 3,13 C3,20.5 15,38 15,38 C15,38 27,20.5 27,13 C27,6.373 21.627,1 15,1 z" fill="#6A0DAD" stroke="white" stroke-width="2"/>
            <text x="15" y="20" text-anchor="middle" fill="white" font-size="12" font-weight="bold">${count}</text>
        </svg>
    `;
    return L.divIcon({
        html: svgIcon,
        className: 'custom-marker',
        iconSize: [30, 40],
        iconAnchor: [15, 40],
        popupAnchor: [0, -40]
    });
}

// Geocoding function using PHP proxy to avoid CORS issues
async function geocodeLocation(locationName, options = {}) {
    const { isInstitution = false, countryHint = 'in' } = options;
    if (!locationName || locationName === 'Unknown' || locationName.trim() === '') {
        return null;
    }
    
    try {
        const baseQuery = locationName.trim();
        const query = (!isInstitution && !baseQuery.toLowerCase().includes('india') && countryHint)
            ? `${baseQuery}, India`
            : baseQuery;

        const params = new URLSearchParams({
            q: baseQuery.trim(), // Send original query, let PHP handle formatting
            institution: isInstitution ? '1' : '0',
            country: countryHint || 'in'
        });

        // Use PHP proxy instead of direct API call to avoid CORS
        const response = await fetch(
            `geocode.php?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );
        
        if (!response.ok) {
            console.warn(`Geocoding failed for "${locationName}": HTTP ${response.status}`);
            return null;
        }
        
        const data = await response.json();
        
        // Check if it's an error response
        if (data && data.error) {
            console.warn(`Geocoding error for "${locationName}": ${data.error}`);
            return null;
        }
        
        if (data && Array.isArray(data) && data.length > 0) {
            const coords = [parseFloat(data[0].lat), parseFloat(data[0].lon)];
            console.log(`Geocoded "${locationName}" to [${coords[0]}, ${coords[1]}]`);
            return coords;
        }
        
        console.warn(`No results found for "${locationName}"`);
        return null;
    } catch (error) {
        // Silently fail and return null - don't spam console with errors
        console.warn(`Geocoding failed for "${locationName}" - will use fallback coordinates`);
        return null;
    }
}

// Cache for geocoded locations to avoid repeated API calls
const geocodingCache = new Map();

const institutionCoordinateOverrides = {
    'massachusetts institute of technology': [42.3601, -71.0942],
    'mit': [42.3601, -71.0942],
    'stanford university': [37.4275, -122.1697],
    'harvard university': [42.3770, -71.1167],
    'university of british columbia': [49.2606, -123.2460],
    'ubc': [49.2606, -123.2460],
    'university of toronto': [43.6629, -79.3957],
    'university of waterloo': [43.4723, -80.5449],
    'university of cambridge': [52.2043, 0.1149],
    'university of oxford': [51.7548, -1.2544],
    'national university of singapore': [1.2966, 103.7764],
    'nanyang technological university': [1.3483, 103.6831],
    'indian institute of technology delhi': [28.5450, 77.1926],
    'indian institute of technology bombay': [19.1320, 72.9150],
    'iit delhi': [28.5450, 77.1926],
    'iit bombay': [19.1320, 72.9150],
    'indian institute of management ahmedabad': [23.0315, 72.5586],
    'iim ahmedabad': [23.0315, 72.5586],
    'london school of economics': [51.5145, -0.1160],
    'university of melbourne': [-37.7982, 144.9614],
    'monash university': [-37.9100, 145.1340],
    'university of sydney': [-33.8888, 151.1870],
    'university of new south wales': [-33.9173, 151.2313],
    'unsw': [-33.9173, 151.2313],
    'university of california los angeles': [34.0689, -118.4452],
    'university of california, los angeles': [34.0689, -118.4452],
    'ucla': [34.0689, -118.4452],
    'university of california san diego': [32.8801, -117.2340],
    'uc san diego': [32.8801, -117.2340],
    'new york university': [40.7295, -73.9965],
    'nyu': [40.7295, -73.9965],
    'arizona state university': [33.4242, -111.9281],
    'carnegie mellon university': [40.4435, -79.9435],
    'georgia institute of technology': [33.7756, -84.3963],
    'pennsylvania state university': [40.7982, -77.8599],
    'the university of hong kong': [22.2830, 114.1371],
    'hong kong university of science and technology': [22.3364, 114.2655],
    'hkust': [22.3364, 114.2655],
    'jawaharlal nehru university': [28.5402, 77.1660],
    'delhi university': [28.6880, 77.2140],
    'university of delhi': [28.6880, 77.2140],
    'indraprastha institute of information technology delhi': [28.5450, 77.2732],
    'iiit delhi': [28.5450, 77.2732],
    'ashoka university': [28.8773, 77.1025],
    'azim premji university': [12.8996, 77.6216],
    'christ university': [12.9345, 77.6050],
    'banaras hindu university': [25.2677, 82.9913],
    'jindal global university': [28.8715, 77.0677],
    'op jindal global university': [28.8715, 77.0677],
    'symbiosis international university': [18.5440, 73.8128],
    'amity university': [28.5388, 77.3309],
    'university of queensland': [-27.4975, 153.0137],
    'technical university of munich': [48.2620, 11.6670],
    'tum': [48.2620, 11.6670],
    'rwth aachen university': [50.7780, 6.0609],
    'university of twente': [52.2400, 6.8528],
    'delft university of technology': [51.9998, 4.3737],
    'tu delft': [51.9998, 4.3737],
    'polytechnic university of milan': [45.4789, 9.2276],
    'politecnico di milano': [45.4789, 9.2276],
    'sorbonne university': [48.8462, 2.3458],
    'paris-sorbonne': [48.8514, 2.3488]
};

function isDefaultCoords(coords) {
    return !coords || (coords[0] === 20.5937 && coords[1] === 78.9629);
}

function buildInstitutionQueries(name) {
    if (!name) return [];
    const original = name.trim();
    const queries = new Set();
    queries.add(original);

    const parenthetical = Array.from(original.matchAll(/\(([^)]+)\)/g)).map(match => match[1]);
    const withoutParentheses = original.replace(/\s*\([^)]*\)\s*/g, ' ').replace(/\s+/g, ' ').trim();
    if (withoutParentheses && withoutParentheses !== original) {
        queries.add(withoutParentheses);
    }

    const segments = withoutParentheses.split(/[|•-]/).map(s => s.trim()).filter(Boolean);
    if (segments.length > 1) {
        queries.add(segments.join(' '));
        queries.add(segments[0]);
        segments.slice(1).forEach(segment => {
            if (segment) {
                queries.add(`${segments[0]} ${segment}`);
                queries.add(segment);
            }
        });
    }

    const commaParts = withoutParentheses.split(',').map(s => s.trim()).filter(Boolean);
    if (commaParts.length > 1) {
        const base = commaParts[0];
        const rest = commaParts.slice(1).join(' ');
        queries.add(base);
        queries.add(`${base} ${rest}`);
        commaParts.slice(1).forEach(part => queries.add(`${base} ${part}`));
    }

    parenthetical.forEach(hint => {
        const hintClean = hint.replace(/[^a-z0-9\s,]/gi, ' ').trim();
        if (hintClean) {
            queries.add(`${withoutParentheses} ${hintClean}`);
        }
    });

    const base = withoutParentheses.split(',')[0] || withoutParentheses;
    if (base && !/(university|college|institute|school|academy|faculty)/i.test(base)) {
        queries.add(`${base} university`);
        queries.add(`${base} college`);
        queries.add(`${base} campus`);
    }

    queries.add(`${withoutParentheses} campus`);

    return Array.from(queries).filter(Boolean);
}

async function getLocationCoordinates(locationName, options = {}) {
    const { isInstitution = false } = options;
    console.log(`Getting coordinates for: "${locationName}" (${isInstitution ? 'institution' : 'location'})`);
    
    const cacheKey = `${isInstitution ? 'edu' : 'loc'}|${locationName}`;
    if (geocodingCache.has(cacheKey)) {
        console.log(`Cache hit for: "${locationName}"`);
        return geocodingCache.get(cacheKey);
    }

    if (isInstitution) {
        const overrideKey = locationName.trim().toLowerCase();
        if (institutionCoordinateOverrides[overrideKey]) {
            const coords = institutionCoordinateOverrides[overrideKey];
            geocodingCache.set(cacheKey, coords);
            console.log(`Using institution override for "${locationName}": [${coords[0]}, ${coords[1]}]`);
            return coords;
        }

        const queries = buildInstitutionQueries(locationName);
        console.log(`Institution queries for "${locationName}":`, queries);
        for (const [index, query] of queries.entries()) {
            const coords = await geocodeLocation(query, { isInstitution: true });
            if (!isDefaultCoords(coords)) {
                geocodingCache.set(cacheKey, coords);
                console.log(`Institution geocoding success for "${locationName}" via "${query}": [${coords[0]}, ${coords[1]}]`);
                return coords;
            }
            if (index < queries.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 400));
            }
        }

        console.warn(`Could not find coordinates for institution "${locationName}", skipping marker`);
        const defaultCoords = [20.5937, 78.9629];
        geocodingCache.set(cacheKey, defaultCoords);
        return defaultCoords;
    }
    
    if (!isInstitution) {
    const cityCoords = getCityCoordinates(locationName);
        if (!isDefaultCoords(cityCoords)) {
        console.log(`Using curated coordinates for "${locationName}": [${cityCoords[0]}, ${cityCoords[1]}]`);
            geocodingCache.set(cacheKey, cityCoords);
        return cityCoords;
        }
    }
    
    console.log(`Trying API geocoding for: "${locationName}"`);
    const coords = await geocodeLocation(locationName, { isInstitution });
    if (!isDefaultCoords(coords)) {
        console.log(`API geocoding successful for "${locationName}": [${coords[0]}, ${coords[1]}]`);
        geocodingCache.set(cacheKey, coords);
        return coords;
    }
    
    if (!isInstitution) {
        const fallback = getCityCoordinates(locationName);
        console.warn(`Could not find coordinates for "${locationName}", using fallback [${fallback[0]}, ${fallback[1]}]`);
        geocodingCache.set(cacheKey, fallback);
        return fallback;
    }

    console.warn(`Could not find coordinates for institution "${locationName}", skipping marker`);
    const defaultCoords = [20.5937, 78.9629];
    geocodingCache.set(cacheKey, defaultCoords);
    return defaultCoords;
}

function getCityCoordinates(cityName) {
    // Ignore not-applicable inputs like random letters, meaningless names, numerics, or symbols
    if (!cityName || cityName.trim() === '' || cityName.length < 2 || !/[a-zA-Z]/.test(cityName)) {
        return [20.5937, 78.9629]; // Default to center of India
    }

    // Manually curated coordinates for common work locations and cities
    const cityCoords = {
    // Major Tech Hubs
        'bangalore': [12.9716, 77.5946],
        'bengaluru': [12.9716, 77.5946],
        'hyderabad': [17.3850, 78.4867],
        'mumbai': [19.0760, 72.8777],
        'delhi': [28.6139, 77.2090],
        'chennai': [13.0827, 80.2707],
        'Kolkata': [22.5726, 88.3639],
        'Pune': [18.5204, 73.8567],
        'Ahmedabad': [23.0225, 72.5714],
        'Gurgaon': [28.4595, 77.0266],
        'Gurugram': [28.4595, 77.0266],
        'Noida': [28.5355, 77.3910],
        'Greater Noida': [28.4744, 77.5040],
        'Jaipur': [26.9124, 75.7873],
        'Goa': [15.2993, 74.1240],
        'Kochi': [9.9312, 76.2673],
        'Cochin': [9.9312, 76.2673],
        'Chandigarh': [30.7333, 76.7794],
        'Indore': [22.7196, 75.8577],
        'Bhopal': [23.2599, 77.4126],
        'Lucknow': [26.8467, 80.9462],
        'Nagpur': [21.1458, 79.0882],
        'Visakhapatnam': [17.6869, 83.2185],
        'Vizag': [17.6869, 83.2185],
        'Bhubaneswar': [20.2961, 85.8245],
        'Coimbatore': [11.0168, 76.9558],
        'Mysore': [12.2958, 76.6394],
        'Mysuru': [12.2958, 76.6394],
        'Thiruvananthapuram': [8.5241, 76.9366],
        'Trivandrum': [8.5241, 76.9366],
        'Surat': [21.1702, 72.8311],
        'Vadodara': [22.3072, 73.1812],
        'Rajkot': [22.3039, 70.8022],
        'NCR': [28.6139, 77.2090],
        'New Delhi': [28.6139, 77.2090],
        'Delhi NCR': [28.6139, 77.2090],
        'Faridabad': [28.4089, 77.3178],
        'Ghaziabad': [28.6692, 77.4538],
        // State Capitals
        'Jaipur': [26.9124, 75.7873],
        'Lucknow': [26.8467, 80.9462],
        'Patna': [25.5941, 85.1376],
        'Ranchi': [23.3441, 85.3096],
        'Bhopal': [23.2599, 77.4126],
        'Raipur': [21.2514, 81.6296],
        'Gandhinagar': [23.2156, 72.6369],
        'Panaji': [15.4909, 73.8278],
        'Shimla': [31.1048, 77.1734],
        'Srinagar': [34.0837, 74.7973],
        'Dehradun': [30.3165, 78.0322],
        'Imphal': [24.8170, 93.9368],
        'Shillong': [25.5788, 91.8933],
        'Aizawl': [23.7307, 92.7173],
        'Kohima': [25.6747, 94.1086],
        'Itanagar': [27.1022, 93.6919],
        'Dispur': [26.1433, 91.7898],
        'Gangtok': [27.3389, 88.6065],
        'Agartala': [23.8315, 91.2868],
        // Karnataka
        'Mangalore': [12.9141, 74.8560],
        'Mangaluru': [12.9141, 74.8560],
        'Hubli': [15.3647, 75.1240],
        'Belgaum': [15.8497, 74.4977],
        'Belagavi': [15.8497, 74.4977],
        // Tamil Nadu  
        'Madurai': [9.9252, 78.1198],
        'Salem': [11.6643, 78.1460],
        'Tiruppur': [11.1085, 77.3411],
        'Erode': [11.3410, 77.7172],
        'Vellore': [12.9165, 79.1325],
        // Maharashtra
        'Nagpur': [21.1458, 79.0882],
        'Nashik': [19.9975, 73.7898],
        'Aurangabad': [19.8762, 75.3433],
        'Thane': [19.2183, 72.9781],
        'Navi Mumbai': [19.0330, 73.0297],
        'Kalyan': [19.2403, 73.1305],
        // Kerala
        'Kozhikode': [11.2588, 75.7804],
        'Calicut': [11.2588, 75.7804],
        'Kollam': [8.8932, 76.6141],
        'Thrissur': [10.5276, 76.2144],
        'Kannur': [11.8745, 75.3704],
        'Kottayam': [9.5916, 76.5222],
        'Palakkad': [10.7867, 76.6548],
        // Telangana & Andhra Pradesh
        'Warangal': [17.9689, 79.5941],
        'Vijayawada': [16.5062, 80.6480],
        'Guntur': [16.3067, 80.4365],
        'Nellore': [14.4426, 79.9865],
        'Tirupati': [13.6288, 79.4192],
        // West Bengal
        'Siliguri': [26.7271, 88.3953],
        'Durgapur': [23.5204, 87.3119],
        'Asansol': [23.6739, 86.9524],
        'Howrah': [22.5958, 88.2636],
        // Gujarat
        'Baroda': [22.3072, 73.1812],
        'Anand': [22.5645, 72.9289],
        'Gandhinagar': [23.2156, 72.6369],
        'Jamnagar': [22.4707, 70.0577],
        'Bhavnagar': [21.7645, 72.1519],
        'IIT': [19.1334, 72.9137],
        'IIT Bombay': [19.1334, 72.9137],
        'IIT Delhi': [28.5452, 77.1925],
        'IIT Madras': [12.9915, 80.2337],
        'IIT Kharagpur': [22.3164, 87.3101],
        'IIT Kanpur': [26.5120, 80.2328],
        'IIT Roorkee': [29.8651, 77.8974],
        'IIM': [23.2304, 77.4035],
        'IIM Ahmedabad': [23.0326, 72.5325],
        'IIM Bangalore': [12.9363, 77.6935],
        'IIM Calcutta': [22.4480, 88.3565],
        'IISc': [13.0210, 77.5666],
        'BITS': [28.4056, 77.1907],
        'BITS Pilani': [28.4056, 77.1907],
        'Harvard': [42.3770, -71.1167],
        'MIT': [42.3601, -71.0942],
        'Stanford': [37.4275, -122.1697],
        'Oxford': [51.7550, -1.2549],
        'Cambridge': [52.2053, 0.1174],
        'NUS': [1.2966, 103.7764],
        'NTU': [1.3483, 103.6831],
        // Common universities and institutions
        'Delhi University': [28.6893, 77.2106],
        'University of Delhi': [28.6893, 77.2106],
        'JNU': [28.5431, 77.1667],
        'Jawaharlal Nehru University': [28.5431, 77.1667],
        'DU': [28.6893, 77.2106],
        'Delhi University': [28.6893, 77.2106],
        'Shyama Prasad Mukherji College': [28.6939, 77.1633],
        'SPMC': [28.6939, 77.1633],
    };
    
    const searchName = cityName.toLowerCase().trim();
    
    // Extract city name if in "City, State" format
    let cityOnly = searchName;
    if (searchName.includes(',')) {
        cityOnly = searchName.split(',')[0].trim();
        console.log(`Extracted city from "${cityName}": "${cityOnly}"`);
    }
    
    // Exact match first (try both full string and city-only)
    for (const [key, coords] of Object.entries(cityCoords)) {
        const keyLower = key.toLowerCase().trim();
        if (searchName === keyLower || cityOnly === keyLower) {
            console.log(`Exact match found: "${cityName}" -> "${key}" -> [${coords[0]}, ${coords[1]}]`);
            return coords;
        }
    }
    
    // Fuzzy matching - check if city name is contained in the search term
    for (const [key, coords] of Object.entries(cityCoords)) {
        const keyLower = key.toLowerCase().trim();
        
        // Check if the key matches the city portion
        if (cityOnly.includes(keyLower) || keyLower.includes(cityOnly)) {
            if (cityOnly.length > 2 && keyLower.length > 2) { // Ensure meaningful match
                console.log(`Fuzzy match found: "${cityName}" matches "${key}" -> [${coords[0]}, ${coords[1]}]`);
                return coords;
            }
        }
    }
    
    // Check for common keywords (Delhi, University, College, etc.)
    const keywordMap = {
        'delhi': [28.6139, 77.2090],
        'university': [20.5937, 78.9629], // Center of India
        'college': [20.5937, 78.9629],
        'institute': [20.5937, 78.9629],
        'iit': [19.1334, 72.9137], // Default to IIT Bombay
        'iim': [23.2304, 77.4035], // Default to IIM Indore
    };
    
    for (const [keyword, coords] of Object.entries(keywordMap)) {
        if (searchName.includes(keyword)) {
            console.log(`Keyword match found: "${cityName}" contains "${keyword}" -> [${coords[0]}, ${coords[1]}]`);
            return coords;
        }
    }
    
    const cityPatterns = [
        /(\w+),?\s*(maharashtra|karnataka|tamil nadu|delhi|west bengal|telangana|gujarat|rajasthan|punjab|haryana|uttar pradesh|bihar)/i,
        /university of (\w+)/i,
        /(\w+) (university|college|institute)/i,
        /(\w+)\s+(university|college|institute)/i,
        /(university|college|institute) of (\w+)/i,
    ];
    
    for (const pattern of cityPatterns) {
        const match = cityName.match(pattern);
        if (match) {
            const city = match[1] || match[2] || '';
            if (city && city.length > 2) {
                for (const [key, coords] of Object.entries(cityCoords)) {
                    if (city.toLowerCase().includes(key.toLowerCase()) || 
                        key.toLowerCase().includes(city.toLowerCase())) {
                        return coords;
                    }
                }
            }
        }
    }
    
    const commonCityKeywords = ['delhi', 'mumbai', 'bangalore', 'hyderabad', 'chennai', 'kolkata', 'pune'];
    for (const keyword of commonCityKeywords) {
        if (searchName.includes(keyword)) {
            for (const [key, coords] of Object.entries(cityCoords)) {
                if (key.toLowerCase().includes(keyword)) {
                    return coords;
                }
            }
        }
    }
    
    return [20.5937, 78.9629]; // Default to center of India
}

function determineLocationType(alumniList, locationKey) {
    const statuses = alumniList.map(p => (p.statusType || p.currentStatus || '').toLowerCase());
    const hasStudying = statuses.includes('studying');
    const hasWorking = statuses.includes('working');

    if (hasStudying && !hasWorking) return 'studying';
    if (hasWorking && !hasStudying) return 'working';

    // Heuristic based on location name
    const name = (locationKey || '').toLowerCase();
    const institutionPattern = /(university|college|institute|school|academy|faculty|campus)/;
    if (institutionPattern.test(name)) {
        return 'studying';
    }

    return hasStudying ? 'mixed' : 'working';
}

async function updateMapWithAlumniData() {
    // Clear existing markers
    alumniMarkers.forEach(marker => map.removeLayer(marker));
    alumniMarkers = [];
    
    // Get data from window (set by PHP)
    const alumniData = window.alumniData || [];
    
    console.log('updateMapWithAlumniData - Data check:', {
        hasWindowData: !!window.alumniData,
        dataLength: alumniData.length,
        isArray: Array.isArray(alumniData)
    });
    
    if (!alumniData || alumniData.length === 0) {
        console.warn('No alumni data available for map!', {
            windowAlumniData: window.alumniData,
            alumniData: alumniData
        });
        return;
    }
    
    console.log('Starting map update with', alumniData.length, 'alumni records');
    
    if (alumniData.length > 0) {
        console.log('Sample alumni data:', alumniData[0]);
    }
    
    // Process alumni by their CURRENT WORK locations
    const locationMap = new Map();
    let skippedCount = 0;
    
    for (const person of alumniData) {
        let locationKey = '';
        let locationName = '';
        let displayInfo = '';
        
        const statusRaw = (person.currentStatus || '').trim();
        const status = statusRaw.toLowerCase();
        const education = person.education || {};
        const institution = (education.institution || education.university || '').trim();
        const degree = (education.degree || '').trim();
        
        // Treat "placed" as "working" for map location purposes
        const isWorking = status === 'working' || status === 'placed';

        if (isWorking && person.location && person.location.trim() !== '') {
            locationKey = person.location.trim();
            locationName = person.location.trim();
            displayInfo = person.company ? `${status === 'placed' ? 'Placed at' : 'Working at'} ${person.company}` : (status === 'placed' ? 'Placed' : 'Working');
            console.log(`${person.name}: Using work location = "${locationName}"`);
        } else if (isWorking && person.city && person.city.trim() !== '') {
            locationKey = person.city.trim();
            locationName = person.city.trim();
            displayInfo = person.company ? `${status === 'placed' ? 'Placed at' : 'Working at'} ${person.company}` : (status === 'placed' ? 'Placed' : 'Working');
            console.log(`${person.name}: Using city fallback for work = "${locationName}"`);
        } else if (status === 'studying' && institution) {
            locationKey = institution;
            locationName = institution;
            displayInfo = degree ? `Studying ${degree}` : 'Pursuing higher studies';
            console.log(`${person.name}: Using institution for studies = "${institution}"`);
        } else if (person.location && person.location.trim() !== '') {
            locationKey = person.location.trim();
            locationName = person.location.trim();
            displayInfo = person.company ? `Working at ${person.company}` : 'Alumni';
            console.log(`${person.name}: Using general location = "${locationName}"`);
        } else if (person.city && person.city.trim() !== '') {
            locationKey = person.city.trim();
            locationName = person.city.trim();
            displayInfo = person.company ? `${person.company}` : 'Alumni';
            console.log(`${person.name}: Using city fallback = "${locationName}"`);
        } else if (status === 'studying' && degree) {
            // As a very last resort, use degree name to geocode
            locationKey = degree;
            locationName = degree;
            displayInfo = `Pursuing ${degree}`;
            console.log(`${person.name}: Using degree fallback for studies = "${degree}"`);
        } else {
            console.log(`Skipping ${person.name} - no location or institution data (location: "${person.location}", city: "${person.city}", institution: "${institution}")`);
            skippedCount++;
            continue;
        }
        
        const mapKey = locationKey;
        if (!locationMap.has(mapKey)) {
            locationMap.set(mapKey, []);
        }
        locationMap.get(mapKey).push({
            ...person, 
            displayName: locationName,
            displayInfo,
            statusType: statusRaw,
            institutionName: institution,
            universityName: education.university || '',
            degreeName: degree
        });
    }
    
    console.log(`Grouped into ${locationMap.size} unique locations (${skippedCount} alumni skipped due to missing location data)`);
    
    if (skippedCount > 0) {
        console.warn(`WARNING: ${skippedCount} alumni don't have location data and won't appear on the map!`);
        console.warn(`TIP: Alumni without location data won't appear on the map`);
    }
    
    // Geocode locations and create markers with rate limiting
    const locations = [];
    let locationId = 1;
    let apiCallCount = 0;
    
    for (const [locationKey, people] of locationMap.entries()) {
        // Add delay to respect API rate limits (1 request per second)
        if (apiCallCount > 0) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        console.log(`Geocoding location: "${locationKey}"`);
        const locationType = determineLocationType(people, locationKey);
        const isInstitution = locationType === 'studying';
        const institutionPattern = /(university|college|institute|school|academy|campus)/i;
        const treatAsInstitution = isInstitution || institutionPattern.test(locationKey);
        const coords = await getLocationCoordinates(locationKey, { isInstitution: treatAsInstitution });
        apiCallCount++;
        console.log(`Coordinates for "${locationKey}": [${coords[0]}, ${coords[1]}]`);

        if (treatAsInstitution && coords[0] === 20.5937 && coords[1] === 78.9629) {
            console.warn(`Skipping institution "${locationKey}" due to unresolved coordinates`);
            continue;
        }
        
        const firstPerson = people[0];
        
        const location = {
            id: locationId++,
            name: firstPerson.displayName,
            city: firstPerson.city || locationKey,
            state: firstPerson.state || '',
            count: people.length,
            lat: coords[0],
            lng: coords[1],
            type: locationType,
            alumni: people.map(p => ({
                name: p.name,
                company: p.company || '',
                role: p.role || '',
                year: p.year || 0,
                achievements: p.achievements || [],
                institution: p.institutionName || p.education?.institution || '',
                university: p.universityName || p.education?.university || '',
                degree: p.degreeName || p.education?.degree || '',
                status: p.statusType || p.currentStatus || '',
                displayInfo: p.displayInfo || ''
            }))
        };
        location.workingCount = location.alumni.filter(a => (a.status || '').toLowerCase() === 'working').length;
        location.studyingCount = location.alumni.filter(a => (a.status || '').toLowerCase() === 'studying').length;
        
        locations.push(location);
        console.log(`Created marker for ${location.name} with ${location.count} alumni`);
        
        // Add marker to map
        const marker = L.marker([location.lat, location.lng], {
            icon: createPurpleIcon(location.count)
        }).addTo(map);
        
        // Create popup content showing place name and individual alumni with their companies
        const alumniList = location.alumni.map(alumni => {
            const isWorking = (alumni.status || '').toLowerCase() === 'working';
            const companyInfo = isWorking && alumni.company ? ` - ${alumni.company}` : '';
            return `<div style="margin: 5px 0; font-size: 13px; color: #4b5563;">${escapeHtml(alumni.name)}${companyInfo}</div>`;
        }).join('');
        
        const popupContent = `
            <div style="padding: 10px; min-width: 220px; max-width: 300px;">
                <h3 style="margin: 0 0 10px 0; color: #9333ea; font-size: 16px; font-weight: 600;">${escapeHtml(location.name)}</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    ${alumniList}
                </div>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        marker.on('click', () => handleLocationClick(location));
        
        alumniMarkers.push(marker);
    }
    
    console.log(`Successfully created ${locations.length} map markers`);
    console.log('Map markers summary:', locations.map(l => `${l.name} (${l.count} alumni)`));
    
    // Update map statistics
    updateMapStats(locations);
    
    // Update location buttons
    updateLocationButtons(locations);
    
    console.log('Map update complete!');
}

function updateMapStats(locations) {
    const totalAlumni = locations.reduce((sum, loc) => sum + loc.count, 0);
    const states = new Set(locations.map(loc => loc.state)).size;
    
    document.getElementById('mapTotalAlumni').textContent = totalAlumni;
    document.getElementById('mapStates').textContent = states;
    document.getElementById('mapCities').textContent = locations.length;
}

function updateLocationButtons(locations) {
    const buttonsContainer = document.getElementById('mapLocationButtons');
    // Check if container exists - it may not be present on all pages
    if (!buttonsContainer) {
        console.log('Location buttons container not found, skipping button update');
        return;
    }
    
    buttonsContainer.innerHTML = '';
    
    locations.forEach(location => {
        const button = document.createElement('button');
        button.className = 'location-button';
        const displayName = location.name || location.city || 'Unknown';
        button.textContent = `${displayName} (${location.count})`;
        button.addEventListener('click', () => handleLocationClick(location));
        buttonsContainer.appendChild(button);
    });
}

function handleLocationClick(location) {
    selectedLocation = selectedLocation?.id === location.id ? null : location;
    updateLocationButtonsState();
    updateMapSidebar();
}

function updateLocationButtonsState() {
    const buttons = document.querySelectorAll('.location-button');
    buttons.forEach(button => {
        button.classList.remove('active');
        if (selectedLocation) {
            const locationName = selectedLocation.name || selectedLocation.city || '';
            if (button.textContent.includes(locationName)) {
                button.classList.add('active');
            }
        }
    });
}

function updateMapSidebar() {
    const sidebarTitle = document.getElementById('mapSidebarTitle');
    const sidebarContent = document.getElementById('mapSidebarContent');
    
    if (selectedLocation) {
        sidebarTitle.textContent = `${selectedLocation.name}`;

        const workingCount = selectedLocation.workingCount ?? selectedLocation.alumni.filter(a => (a.status || '').toLowerCase() === 'working').length;
        const studyingCount = selectedLocation.studyingCount ?? selectedLocation.alumni.filter(a => (a.status || '').toLowerCase() === 'studying').length;
        const statusSummaryParts = [];
        if (workingCount) statusSummaryParts.push(`${workingCount} working`);
        if (studyingCount) statusSummaryParts.push(`${studyingCount} studying`);
        const statusSummary = statusSummaryParts.length ? statusSummaryParts.join(' • ') : 'Alumni';
        
        sidebarContent.innerHTML = `
            <div class="map-sidebar-selected">
                <div class="map-sidebar-stats">
                    <div class="map-sidebar-stat-number">${selectedLocation.count}</div>
                    <div class="map-sidebar-stat-label">${statusSummary}</div>
                </div>
                <div class="map-sidebar-alumni-list">
                    ${selectedLocation.alumni.map(alumni => {
                        const status = (alumni.status || '').toLowerCase();
                        let primaryInfo = '';

                        if (status === 'working') {
                            if (alumni.role && alumni.company) {
                                primaryInfo = `
                                <div class="map-sidebar-alumni-company">
                                    <i class="fas fa-briefcase"></i>
                                    ${alumni.role} at ${alumni.company}
                                </div>
                            `;
                        } else if (alumni.company) {
                                primaryInfo = `
                                <div class="map-sidebar-alumni-company">
                                    <i class="fas fa-building"></i>
                                    ${alumni.company}
                                    </div>
                                `;
                            }
                        } else if (status === 'studying') {
                            const institution = alumni.institution || alumni.university || selectedLocation.name;
                            const degree = alumni.degree || '';
                            const studyText = degree ? `${degree} at ${institution}` : `Studying at ${institution}`;
                            primaryInfo = `
                                <div class="map-sidebar-alumni-company">
                                    <i class="fas fa-university"></i>
                                    ${studyText}
                                </div>
                            `;
                        } else if (alumni.displayInfo) {
                            primaryInfo = `
                                <div class="map-sidebar-alumni-company">
                                    <i class="fas fa-user"></i>
                                    ${alumni.displayInfo}
                                </div>
                            `;
                        }
                        
                        let experienceInfo = '';
                        if (alumni.achievements && alumni.achievements.length > 0) {
                            experienceInfo = `
                                <div class="map-sidebar-alumni-achievements">
                                    <i class="fas fa-star"></i>
                                    ${alumni.achievements[0]}
                                </div>
                            `;
                        }
                        
                        return `
                        <div class="map-sidebar-alumni-item">
                            <div class="map-sidebar-alumni-header">
                                <h4 class="map-sidebar-alumni-name">${alumni.name}</h4>
                                <span class="map-sidebar-alumni-year">Class of ${alumni.year}</span>
                            </div>
                            <div class="map-sidebar-alumni-info">
                                ${primaryInfo}
                                ${experienceInfo}
                            </div>
                        </div>
                    `;
                    }).join('')}
                </div>
            </div>
        `;
    } else {
        sidebarTitle.textContent = 'Select a Location';
        
        const totalAlumni = alumniData.filter(a => a.location || a.city).length;
        const uniqueLocations = new Set();
        alumniData.forEach(a => {
            if (a.location) uniqueLocations.add(a.location);
            else if (a.city) uniqueLocations.add(a.city);
        });
        const totalLocations = uniqueLocations.size;
        
        sidebarContent.innerHTML = `
            <div class="map-sidebar-placeholder">
                <i class="fas fa-map-marker-alt"></i>
                <p>Click on a location marker or button to view alumni working in that area</p>
                <div class="map-sidebar-summary">
                    <strong>${totalAlumni}</strong> alumni across <strong>${totalLocations}</strong> work locations
                </div>
            </div>
        `;
    }
}

// Alumni data initialization
function initializeAlumniData() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
    loadingOverlay.style.display = 'flex';
    }

    // Clear geocoding cache to ensure fresh coordinates
    if (typeof geocodingCache !== 'undefined' && geocodingCache && geocodingCache.clear) {
    geocodingCache.clear();
    }

    // Use data from PHP (already loaded in global scope)
    setTimeout(() => {
        // Always hide loading overlay first - no matter what
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            overlay.style.visibility = 'hidden';
            overlay.style.opacity = '0';
            overlay.classList.add('is-hidden');
        }
        
        try {
            // Then update components
            if (typeof updateDashboard === 'function') {
        updateDashboard();
            }
            if (typeof updateMapWithAlumniData === 'function') {
        updateMapWithAlumniData();
            }
            // Initialize alumni grid with 6 random cards
            if (typeof updateAlumniGrid === 'function') {
                updateAlumniGrid();
            }
        } catch (error) {
            console.error('Error in initializeAlumniData:', error);
            // Ensure overlay is hidden on error
            const errorOverlay = document.getElementById('loadingOverlay');
            if (errorOverlay) {
                errorOverlay.style.display = 'none';
                errorOverlay.style.visibility = 'hidden';
                errorOverlay.style.opacity = '0';
            }
        }
    }, 500);
    
    // Fallback: Force hide overlay after 2 seconds no matter what
    setTimeout(() => {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            overlay.style.visibility = 'hidden';
            overlay.style.opacity = '0';
            overlay.classList.add('is-hidden');
            console.log('Loading overlay force-hidden after timeout');
        }
    }, 2000);
}

function updateDashboard() {
    // Stats are now populated by PHP, just update the grid
    updateAlumniGrid();
}

function updateAlumniGrid() {
    const alumniGrid = document.getElementById('alumniGrid');
    const noResults = document.getElementById('noResults');

    // If grid doesn't exist, this function shouldn't run (likely on homepage)
    if (!alumniGrid) {
        return;
    }

    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const selectedYear = yearFilter ? yearFilter.value : '';

    // Get all alumni data from window (set by PHP)
    const allAlumniData = window.alumniData || [];
    
    // If no search/filter is active, show only 6 random cards
    const hasActiveFilter = searchTerm !== '' || (selectedYear !== '' && selectedYear !== 'all');
    
    let filteredAlumni;
    if (hasActiveFilter) {
        // When searching/filtering, use ALL data
        filteredAlumni = allAlumniData.filter(alumni => {
            const matchesSearch = !searchTerm || 
                alumni.name.toLowerCase().includes(searchTerm) ||
                (alumni.company && alumni.company.toLowerCase().includes(searchTerm)) ||
                (alumni.role && alumni.role.toLowerCase().includes(searchTerm));
            
            const matchesYear = selectedYear === "all" || selectedYear === '' || 
                alumni.year.toString() === selectedYear;
            
            return matchesSearch && matchesYear;
        });
        } else {
        // When no filter, show 6 random cards
        const shuffled = [...allAlumniData].sort(() => Math.random() - 0.5);
        filteredAlumni = shuffled.slice(0, 6);
    }

    // Clear existing cards and render new ones
    alumniGrid.innerHTML = '';
    
    if (filteredAlumni.length === 0) {
        noResults.style.display = 'block';
        alumniGrid.style.display = 'none';
    } else {
        noResults.style.display = 'none';
        alumniGrid.style.display = 'grid';
        
        // Render cards for filtered alumni
        filteredAlumni.forEach(alumni => {
            const card = createAlumniCardElement(alumni);
            alumniGrid.appendChild(card);
        });
    }
}

function createAlumniCardElement(alumni) {
    const card = document.createElement('div');
    card.className = 'alumni-card';
    
    const statusBadge = alumni.currentStatus ? 
        `<span class="alumni-status-badge ${alumni.currentStatus.toLowerCase()}">${alumni.currentStatus}</span>` : '';
    
    const statusDetails = alumni.statusDetails ? 
        `<p class="alumni-info">${alumni.statusDetails}</p>` : '';
    
    const roleCompany = alumni.role && alumni.company ? 
        `<div class="alumni-info">
            <p class="alumni-role">${alumni.role}</p>
            <p class="alumni-company">${alumni.company}</p>
        </div>` : '';
    
    const location = alumni.location ? 
        `<div class="alumni-location">
            <i class="fas fa-map-marker-alt"></i>
            <span>${alumni.location}</span>
        </div>` : '';
    
    const linkedin = alumni.linkedin ? 
        `<div class="alumni-linkedin">
            <a href="${alumni.linkedin}" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-linkedin"></i>
                View LinkedIn Profile →
            </a>
        </div>` : '';
    
    card.innerHTML = `
        <div class="alumni-card-header">
            <div class="alumni-card-title">${escapeHtml(alumni.name)}</div>
            <div class="alumni-card-subtitle">${escapeHtml(alumni.program)} • ${alumni.year}</div>
            ${statusBadge}
        </div>
        <div class="alumni-card-content">
            ${statusDetails}
            ${roleCompany}
            ${location}
            ${linkedin}
        </div>
    `;
    
    return card;
}

function createAlumniCard(alumni) {
    const statusBadge = alumni.currentStatus ? 
        `<span class="alumni-status-badge ${alumni.currentStatus.toLowerCase()}">${alumni.currentStatus}</span>` : '';
    
    const statusDetails = alumni.statusDetails ? 
        `<p class="alumni-info">${alumni.statusDetails}</p>` : '';
    
    const roleCompany = alumni.role && alumni.company ? 
        `<div class="alumni-info">
            <p class="alumni-role">${alumni.role}</p>
            <p class="alumni-company">${alumni.company}</p>
        </div>` : '';
    
    const location = alumni.location ? 
        `<div class="alumni-location">
            <i class="fas fa-map-marker-alt"></i>
            <span>${alumni.location}</span>
        </div>` : '';
    
    const collegeHelp = alumni.collegeHelp && alumni.collegeHelp.length > 0 ? 
        `<div class="alumni-college-help">
            <p class="alumni-college-help-title">How College Helped:</p>
            <ul class="alumni-college-help-list">
                ${alumni.collegeHelp.map(help => `<li>${help}</li>`).join('')}
            </ul>
        </div>` : '';
    
    const education = alumni.education?.degree ? 
        `<div class="alumni-education">
            <p class="alumni-education-text">
                <strong>Education:</strong> ${alumni.education.degree}
                ${alumni.education.institution ? ` at ${alumni.education.institution}` : ''}
            </p>
        </div>` : '';
    
    const linkedin = alumni.linkedin ? 
        `<div class="alumni-linkedin">
            <a href="${alumni.linkedin}" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-linkedin"></i>
                View LinkedIn Profile →
            </a>
        </div>` : '';
    
    return `
        <div class="alumni-card">
            <div class="alumni-card-header">
                <div class="alumni-card-title">${alumni.name}</div>
                <div class="alumni-card-subtitle">${alumni.program} • ${alumni.year}</div>
                ${statusBadge}
            </div>
            <div class="alumni-card-content">
                ${statusDetails}
                ${roleCompany}
                ${location}
                ${collegeHelp}
                ${education}
                ${linkedin}
            </div>
        </div>
    `;
}

// Search and filter functionality
function initializeSearchFilters() {
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    
    // Add null checks to prevent errors if elements don't exist
    if (searchInput) {
    searchInput.addEventListener('input', updateAlumniGrid);
    }
    
    if (yearFilter) {
    yearFilter.addEventListener('change', updateAlumniGrid);
    }
}

function getFilteredAlumni() {
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const selectedYear = yearFilter ? yearFilter.value : '';

    // Get alumni data from window (set by PHP) or fallback to empty array
    const alumniData = window.alumniData || [];
    if (!alumniData || alumniData.length === 0) {
        return [];
    }

    return alumniData.filter(alumni => {
        const matchesSearch = alumni.name.toLowerCase().includes(searchTerm) ||
                             (alumni.company && alumni.company.toLowerCase().includes(searchTerm)) ||
                             (alumni.role && alumni.role.toLowerCase().includes(searchTerm));
        const matchesYear = selectedYear === "all" || alumni.year.toString() === selectedYear;

        return matchesSearch && matchesYear;
    });
}

// Testimonials functionality
function initializeTestimonials() {
    const prevBtn = document.getElementById('testimonialPrev');
    const nextBtn = document.getElementById('testimonialNext');
    
    if (prevBtn) {
    prevBtn.addEventListener('click', () => changeTestimonial(-1));
    }
    if (nextBtn) {
    nextBtn.addEventListener('click', () => changeTestimonial(1));
    }
    
    // Don't call updateTestimonials here - it will be called by initializeAlumniData
    // This function only sets up event listeners
}

function updateTestimonials() {
    // Use alumniData from global scope (set by PHP) - matching reference implementation
    const alumniData = window.alumniData || (typeof alumniData !== 'undefined' ? alumniData : []);
    
    if (!alumniData || alumniData.length === 0) {
        console.warn('No alumni data for testimonials');
        return;
    }

    const testimonials = alumniData.slice(0, 5).map((alumni, index) => {
        let quote = '';
        if (alumni.currentStatus === 'Working' && alumni.company) {
            quote = `After graduating in ${alumni.year}, I'm now working as ${alumni.role} at ${alumni.company}.`;
            if (alumni.location) {
                quote += ` Located in ${alumni.location}.`;
            }
            if (alumni.experienceYears) {
                quote += ` I have ${alumni.experienceYears} years of experience.`;
            }
        } else if (alumni.currentStatus === 'Studying') {
            quote = `Currently pursuing ${alumni.education?.degree || 'higher education'} at ${alumni.education?.institution || 'University'}.`;
        } else if (alumni.education?.degree) {
            quote = `Completed ${alumni.education.degree} from ${alumni.education.institution}.`;
        } else {
            quote = `Graduated in ${alumni.year} from the ${alumni.program} program.`;
        }

        return {
            id: alumni.id,
            name: alumni.name,
            role: alumni.currentStatus === 'Working' && alumni.company
                ? `${alumni.role} at ${alumni.company}`
                : alumni.statusDetails || 'Alumni',
            year: alumni.year,
            image: `https://ui-avatars.com/api/?name=${encodeURIComponent(alumni.name)}&background=9333ea&color=fff&size=200`,
            quote: quote,
            rating: 5,
            company: alumni.company,
            achievements: alumni.achievements || [],
            currentStatus: alumni.currentStatus
        };
    });

    window.testimonials = testimonials;
    updateTestimonialDisplay();
    // Thumbnails removed - only main testimonial display is shown
}

function updateTestimonialDisplay() {
    console.log('updateTestimonialDisplay called', {
        hasTestimonials: !!window.testimonials,
        testimonialsLength: window.testimonials ? window.testimonials.length : 0,
        currentIndex: currentTestimonialIndex
    });
    
    if (!window.testimonials || window.testimonials.length === 0) {
        console.warn('No testimonials to display');
        return;
    }
    
    // Ensure index is valid
    if (currentTestimonialIndex >= window.testimonials.length) {
        currentTestimonialIndex = 0;
    }
    if (currentTestimonialIndex < 0) {
        currentTestimonialIndex = window.testimonials.length - 1;
    }
    
    const testimonial = window.testimonials[currentTestimonialIndex];
    if (!testimonial) {
        console.error('Testimonial not found at index', currentTestimonialIndex, 'out of', window.testimonials.length);
        return;
    }
    
    // Validate testimonial object has required properties
    if (!testimonial.image || !testimonial.name || !testimonial.quote) {
        console.error('Testimonial missing required properties:', testimonial);
        return;
    }
    
    console.log('Displaying testimonial:', testimonial.name);
    
    const testimonialImage = document.getElementById('testimonialImage');
    const testimonialQuote = document.getElementById('testimonialQuote');
    const testimonialName = document.getElementById('testimonialName');
    const testimonialStatus = document.getElementById('testimonialStatus');
    const testimonialRole = document.getElementById('testimonialRole');
    const testimonialYear = document.getElementById('testimonialYear');
    const testimonialAchievements = document.getElementById('testimonialAchievements');
    
    console.log('DOM elements found:', {
        image: !!testimonialImage,
        quote: !!testimonialQuote,
        name: !!testimonialName,
        role: !!testimonialRole,
        year: !!testimonialYear
    });
    
    if (!testimonialImage || !testimonialQuote || !testimonialName) {
        console.error('Required DOM elements not found!');
        return;
    }
    
    // Set all content with proper null checks
    try {
        // Safe image handling with fallback
        if (testimonial && testimonial.image) {
    testimonialImage.src = testimonial.image;
        } else {
            // Fallback to default avatar if image is missing
            const fallbackName = testimonial && testimonial.name ? encodeURIComponent(testimonial.name) : 'Alumni';
            testimonialImage.src = `https://ui-avatars.com/api/?name=${fallbackName}&background=9333ea&color=fff&size=200`;
        }
        
        testimonialImage.alt = (testimonial && testimonial.name) ? testimonial.name : 'Alumni';
        testimonialQuote.textContent = (testimonial && testimonial.quote) ? testimonial.quote : '';
        testimonialName.textContent = (testimonial && testimonial.name) ? testimonial.name : '';
        
        if (testimonialRole) {
            testimonialRole.textContent = (testimonial && testimonial.role) ? testimonial.role : '';
        }
        
        if (testimonialYear) {
            testimonialYear.textContent = (testimonial && testimonial.year) ? `Class of ${testimonial.year}` : '';
        }
        
        if (testimonialStatus) {
            if (testimonial && testimonial.currentStatus) {
        testimonialStatus.textContent = testimonial.currentStatus;
        testimonialStatus.className = `author-status ${testimonial.currentStatus.toLowerCase()}`;
        testimonialStatus.style.display = 'inline-block';
    } else {
        testimonialStatus.style.display = 'none';
            }
        }
        
        if (testimonialAchievements) {
            const achievements = (testimonial && testimonial.achievements && Array.isArray(testimonial.achievements)) 
                ? testimonial.achievements 
                : [];
            testimonialAchievements.innerHTML = achievements.map(achievement => 
        `<span class="testimonial-achievement">${achievement}</span>`
    ).join('');
        }
        
        console.log('Testimonial content set successfully');
    } catch (error) {
        console.error('Error setting testimonial content:', error);
        // Set fallback content on error
        if (testimonialImage) {
            const fallbackName = testimonial && testimonial.name ? encodeURIComponent(testimonial.name) : 'Alumni';
            testimonialImage.src = `https://ui-avatars.com/api/?name=${fallbackName}&background=9333ea&color=fff&size=200`;
        }
    }
}

function updateTestimonialThumbnails() {
    if (!window.testimonials || window.testimonials.length === 0) return;
    
    const thumbnailsContainer = document.getElementById('testimonialThumbnails');
    if (!thumbnailsContainer) return;
    thumbnailsContainer.innerHTML = window.testimonials.map((testimonial, index) => {
        // Safe access with fallbacks
        const testimonialImage = (testimonial && testimonial.image) 
            ? testimonial.image 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent((testimonial && testimonial.name) ? testimonial.name : 'Alumni')}&background=9333ea&color=fff&size=200`;
        const testimonialName = (testimonial && testimonial.name) ? testimonial.name : 'Alumni';
        const testimonialCompany = (testimonial && testimonial.company) ? testimonial.company : '';
        const testimonialYear = (testimonial && testimonial.year) ? testimonial.year : '';
        
        return `
        <div class="testimonial-thumbnail ${index === currentTestimonialIndex ? 'active' : ''}" 
             onclick="setCurrentTestimonial(${index})">
            <img src="${testimonialImage}" alt="${testimonialName}" class="testimonial-thumbnail-image">
            <div class="testimonial-thumbnail-name">${testimonialName}</div>
            <div class="testimonial-thumbnail-company">${testimonialCompany}</div>
            <div class="testimonial-thumbnail-year">${testimonialYear ? `Class of ${testimonialYear}` : ''}</div>
            <div class="testimonial-thumbnail-rating">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
            </div>
        </div>
    `;
    }).join('');
}

function changeTestimonial(direction) {
    if (!window.testimonials || window.testimonials.length === 0) return;
    
    currentTestimonialIndex = (currentTestimonialIndex + direction + window.testimonials.length) % window.testimonials.length;
    updateTestimonialDisplay();
    // Thumbnails removed - only main testimonial display is shown
}

function setCurrentTestimonial(index) {
    currentTestimonialIndex = index;
    updateTestimonialDisplay();
    // Thumbnails removed - only main testimonial display is shown
}

// Smooth scrolling for anchor links
document.addEventListener('click', function(event) {
    if (event.target.tagName === 'A' && event.target.getAttribute('href').startsWith('#')) {
        event.preventDefault();
        const targetId = event.target.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth' });
        }
    }
});

// Handle hero button clicks
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('hero-btn')) {
        const section = event.target.dataset.section;
        navigateToSection(section);
    }
});

// Handle mobile menu CTA click
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('mobile-menu-cta')) {
        const section = event.target.dataset.section;
        navigateToSection(section);
    }
});

