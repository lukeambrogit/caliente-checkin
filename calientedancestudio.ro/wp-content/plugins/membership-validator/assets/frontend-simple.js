/**
 * Frontend JavaScript pentru Orar Cursuri - Versiune Simplă
 * Păstrează aspectul original, adaugă doar funcționalitatea de extindere
 * 
 * @package OrarCursuri
 * @since 1.0.0
 */

(function() {
    'use strict';
    
    // Evită conflictele și asigură compatibilitatea cu cache-ul
    if (typeof window.OrarCursuri !== 'undefined') {
        return;
    }
    
    // Namespace pentru a evita conflictele
    window.OrarCursuri = {
        
        // Configurare
        config: {
            mobileBreakpoint: 900, // Înapoi la valoarea normală
            storageKey: 'oc_mobile_expanded_state'
        },
        
        // State management
        state: {
            dayStates: new Map(), // Starea fiecărei zile (collapsed/expanded)
            initialized: false
        },
        
        // Inițializare
        init: function() {
            if (this.state.initialized) return;
            
            // Verifică dacă suntem pe mobil
            if (!this.isMobile()) {
                return;
            }
            
            // Așteaptă ca DOM-ul să fie gata
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
            
            this.state.initialized = true;
        },
        
        // Setup principal - PĂSTREAZĂ aspectul original
        setup: function() {
            const scheduleContainer = document.querySelector('.oc-mobile-only.cards');
            if (!scheduleContainer) {
                return;
            }
            
            // Identifică zilele din cardurile existente
            this.identifyDays(scheduleContainer);
            
            // Adaugă doar butoanele de control FĂRĂ să modifice aspectul
            this.addControlsOnly(scheduleContainer);
            
            // Inițial toate zilele sunt extinse (aspectul original)
            this.setInitialState();
            
            // Bind events
            this.bindEvents();
            

        },
        
        // Identifică zilele din cardurile existente
        identifyDays: function(container) {
            const cards = container.querySelectorAll('.card');
            const daysFound = new Set();
            
            cards.forEach(card => {
                const dayBadge = card.querySelector('.badge');
                if (dayBadge) {
                    const dayName = dayBadge.textContent.trim();
                    daysFound.add(dayName);
                    
                    // Marchează cardul cu ziua sa
                    card.setAttribute('data-day', dayName);
                    
                    // Inițial toate sunt ÎNCHISE (doar primul card vizibil)
                    this.state.dayStates.set(dayName, false);
                }
            });
            

        },
        
        // Adaugă DOAR controalele, fără să modifice aspectul existent
        addControlsOnly: function(container) {
            // Buton global - se adaugă DUPĂ container - inițial pentru EXTINDERE
            const globalControls = document.createElement('div');
            globalControls.className = 'oc-simple-controls';
            globalControls.innerHTML = `
                <button type="button" class="oc-toggle-all-btn" data-state="collapsed" 
                        title="Afișează programul complet pentru toate zilele" 
                        aria-label="Extinde toate zilele pentru a vedea programul complet">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Extinde toate zilele
                </button>
            `;
            
            // Inserează DUPĂ container
            container.parentNode.insertBefore(globalControls, container.nextSibling);
            
            // Modifică cardurile existente pentru a fi clickable și adaugă iconițe discrete
            const daysProcessed = new Set();
            const cards = container.querySelectorAll('.card');
            
            cards.forEach(card => {
                const dayName = card.getAttribute('data-day');
                if (dayName && !daysProcessed.has(dayName)) {
                    daysProcessed.add(dayName);
                    
                    // Găsește primul card din această zi și îl fac "master card"
                    const firstCardOfDay = container.querySelector(`[data-day="${dayName}"]`);
                    if (firstCardOfDay) {
                        // Adaugă clasa de master card
                        firstCardOfDay.classList.add('oc-day-master');
                        firstCardOfDay.setAttribute('data-master-day', dayName);
                        
                        // Face tot cardul clickable
                        firstCardOfDay.style.cursor = 'pointer';
                        firstCardOfDay.setAttribute('data-toggle-day', dayName);
                        firstCardOfDay.setAttribute('data-state', 'collapsed');
                        firstCardOfDay.setAttribute('title', 'Apasă pentru a vedea programul complet');
                        firstCardOfDay.setAttribute('aria-label', `Apasă pentru a deschide programul pentru ${dayName}`);
                        
                        // Adaugă iconița de toggle în badge-ul zilei
                        const dayBadge = firstCardOfDay.querySelector('.badge');
                        if (dayBadge && dayBadge.textContent.trim() === dayName) {
                            dayBadge.style.position = 'relative';
                            dayBadge.style.cursor = 'pointer';
                            dayBadge.style.userSelect = 'none';
                            
                            // Adaugă iconița în badge - inițial săgeată în jos (închis)
                            const toggleIcon = document.createElement('span');
                            toggleIcon.className = 'oc-day-toggle-icon';
                            toggleIcon.innerHTML = `
                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="margin-left: 4px; vertical-align: middle;">
                                    <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <span class="oc-help-text">deschide</span>
                            `;
                            dayBadge.appendChild(toggleIcon);
                            
                            // Badge-ul nu mai are atribute separate - cardul master are controlul
                        }
                    }
                }
            });
        },
        
        // Setează starea inițială (toate ÎNCHISE - doar badge-ul zilei vizibil)
        setInitialState: function() {
            // Pentru fiecare zi, ascunde toate cardurile și conținutul master card-ului
            this.state.dayStates.forEach((state, dayName) => {
                const dayCards = document.querySelectorAll(`[data-day="${dayName}"].card`);
                const masterCard = document.querySelector(`[data-master-day="${dayName}"]`);
                
                dayCards.forEach(card => {
                    if (card === masterCard) {
                        // Master card rămâne vizibil dar ascunde conținutul (rooms)
                        card.style.display = 'block';
                        this.hideMasterCardContent(card);
                    } else {
                        card.style.display = 'none'; // Restul se ascund complet
                    }
                });
                
                // Setează iconița în poziția corectă (săgeată în jos pentru închis)
                const dayBadge = document.querySelector(`[data-toggle-day="${dayName}"]`);
                const toggleIcon = dayBadge ? dayBadge.querySelector('.oc-day-toggle-icon svg') : null;
                if (toggleIcon) {
                    toggleIcon.querySelector('path').setAttribute('d', 'M4 6L8 10L12 6');
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            });
        },
        
        // Bind event listeners
        bindEvents: function() {
            document.addEventListener('click', (e) => {
                // Click pe cardul master (toată zona cardului)
                const masterCard = e.target.closest('.oc-day-master[data-toggle-day]');
                if (masterCard) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dayName = masterCard.getAttribute('data-toggle-day');
                    this.toggleDay(dayName);
                    return;
                }
                
                // Buton global
                if (e.target.closest('.oc-toggle-all-btn')) {
                    e.preventDefault();
                    this.toggleAll();
                }
            });
        },
        
        // Toggle pentru o zi specifică
        toggleDay: function(dayName) {
            const isExpanded = this.state.dayStates.get(dayName);
            const newState = !isExpanded;
            
            // Actualizează starea
            this.state.dayStates.set(dayName, newState);
            
            // Găsește toate cardurile pentru această zi
            const dayCards = document.querySelectorAll(`[data-day="${dayName}"].card`);
            const masterCard = document.querySelector(`[data-master-day="${dayName}"]`);
            const toggleIcon = masterCard ? masterCard.querySelector('.oc-day-toggle-icon svg') : null;
            
            if (newState) {
                // Extinde - arată toate cardurile și conținutul complet
                dayCards.forEach(card => {
                    card.style.display = 'block';
                });
                
                // Afișează conținutul complet al master card-ului
                if (masterCard) {
                    this.showMasterCardContent(masterCard);
                }
                
                if (masterCard) {
                    masterCard.setAttribute('data-state', 'expanded');
                    masterCard.setAttribute('title', 'Apasă pentru a ascunde programul');
                    masterCard.setAttribute('aria-label', `Apasă pentru a închide programul pentru ${dayName}`);
                }
                
                if (toggleIcon) {
                    toggleIcon.querySelector('path').setAttribute('d', 'M4 4L12 12M12 4L4 12');
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
                
                // Actualizează textul ajutător
                const helpText = masterCard ? masterCard.querySelector('.oc-help-text') : null;
                if (helpText) {
                    helpText.textContent = 'închide';
                }
            } else {
                // Restrânge - ascunde cardurile și conținutul master card-ului
                dayCards.forEach(card => {
                    if (card !== masterCard) {
                        card.style.display = 'none';
                    }
                });
                
                // Ascunde conținutul master card-ului (păstrează doar badge-ul zilei)
                if (masterCard) {
                    this.hideMasterCardContent(masterCard);
                }
                
                if (masterCard) {
                    masterCard.setAttribute('data-state', 'collapsed');
                    masterCard.setAttribute('title', 'Apasă pentru a vedea programul complet');
                    masterCard.setAttribute('aria-label', `Apasă pentru a deschide programul pentru ${dayName}`);
                }
                
                if (toggleIcon) {
                    toggleIcon.querySelector('path').setAttribute('d', 'M4 6L8 10L12 6');
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
                
                // Actualizează textul ajutător
                const helpText = masterCard ? masterCard.querySelector('.oc-help-text') : null;
                if (helpText) {
                    helpText.textContent = 'deschide';
                }
            }
            
            // Actualizează butonul global
            this.updateGlobalButton();
            

        },
        
        // Toggle pentru toate zilele
        toggleAll: function() {
            const globalBtn = document.querySelector('.oc-toggle-all-btn');
            const currentState = globalBtn.getAttribute('data-state');
            
            if (currentState === 'expanded') {
                // Restrânge toate
                this.state.dayStates.forEach((state, dayName) => {
                    if (state) { // Doar dacă sunt extinse
                        this.toggleDay(dayName);
                    }
                });
                
                globalBtn.setAttribute('data-state', 'collapsed');
                globalBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Extinde toate zilele
                `;
            } else {
                // Extinde toate
                this.state.dayStates.forEach((state, dayName) => {
                    if (!state) { // Doar dacă sunt restrânse
                        this.toggleDay(dayName);
                    }
                });
                
                globalBtn.setAttribute('data-state', 'expanded');
                globalBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Restrânge toate zilele
                `;
            }
        },
        
        // Actualizează butonul global bazat pe starea zilelor
        updateGlobalButton: function() {
            const expandedCount = Array.from(this.state.dayStates.values()).filter(Boolean).length;
            const totalCount = this.state.dayStates.size;
            const globalBtn = document.querySelector('.oc-toggle-all-btn');
            
            if (expandedCount === totalCount) {
                // Toate sunt extinse
                globalBtn.setAttribute('data-state', 'expanded');
                globalBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Restrânge toate zilele
                `;
                globalBtn.setAttribute('title', 'Ascunde programul pentru toate zilele');
                globalBtn.setAttribute('aria-label', 'Restrânge toate zilele pentru a vedea doar numele zilelor');
            } else if (expandedCount === 0) {
                // Toate sunt restrânse
                globalBtn.setAttribute('data-state', 'collapsed');
                globalBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Extinde toate zilele
                `;
                globalBtn.setAttribute('title', 'Afișează programul complet pentru toate zilele');
                globalBtn.setAttribute('aria-label', 'Extinde toate zilele pentru a vedea programul complet');
            } else {
                // Mixt - arată ca extins
                globalBtn.setAttribute('data-state', 'mixed');
                globalBtn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Extinde toate zilele
                `;
            }
        },
        
        // Ascunde conținutul master card-ului (păstrează doar badge-ul zilei)
        hideMasterCardContent: function(masterCard) {
            // Adaugă clasa CSS pentru ascunderea conținutului
            masterCard.classList.add('oc-collapsed-card');
            
            // Backup: forțează ascunderea prin stiluri inline cu !important
            const rooms = masterCard.querySelector('.rooms');
            if (rooms) {
                rooms.style.setProperty('display', 'none', 'important');
            }
            
            // Ascunde badge-ul cu ora (al doilea badge din .top)
            const topSection = masterCard.querySelector('.top');
            if (topSection) {
                const badges = topSection.querySelectorAll('.badge');
                badges.forEach((badge, index) => {
                    if (index > 0) { // Păstrează doar primul badge (ziua)
                        badge.style.setProperty('display', 'none', 'important');
                    }
                });
            }
            

        },
        
        // Afișează conținutul master card-ului
        showMasterCardContent: function(masterCard) {
            // Elimină clasa CSS pentru ascunderea conținutului
            masterCard.classList.remove('oc-collapsed-card');
            
            // Afișează secțiunea cu rooms
            const rooms = masterCard.querySelector('.rooms');
            if (rooms) {
                rooms.style.removeProperty('display');
            }
            
            // Afișează toate badge-urile din .top
            const topSection = masterCard.querySelector('.top');
            if (topSection) {
                const badges = topSection.querySelectorAll('.badge');
                badges.forEach(badge => {
                    badge.style.removeProperty('display');
                });
            }
            

        },
        
        // Verifică dacă suntem pe mobil
        isMobile: function() {
            return window.innerWidth < this.config.mobileBreakpoint;
        }
    };
    
    // Auto-inițializare
    window.OrarCursuri.init();
    
})();
