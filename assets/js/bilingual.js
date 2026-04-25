function toggleLanguage() {
    const currentLang = localStorage.getItem('dmu_lang') || 'en';
    const newLang = currentLang === 'en' ? 'am' : 'en';

    setLanguage(newLang);
}

function setLanguage(lang) {
    localStorage.setItem('dmu_lang', lang);
    const elements = document.querySelectorAll('[data-en]');

    elements.forEach(el => {
        if (lang === 'en') {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                if (el.hasAttribute('data-en-placeholder')) {
                    el.placeholder = el.getAttribute('data-en-placeholder');
                }
            } else {
                el.textContent = el.getAttribute('data-en');
            }
        } else {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                if (el.hasAttribute('data-am-placeholder')) {
                    el.placeholder = el.getAttribute('data-am-placeholder');
                }
            } else {
                el.textContent = el.getAttribute('data-am');
            }
        }
    });

    // Update button text
    const btnText = document.getElementById('lang-text');
    if (btnText) {
        btnText.textContent = lang === 'en' ? 'Amharic' : 'English';
    }

    // Re-apply welcome greeting if available (prevents overriding time-based greeting)
    if (typeof applyWelcomeGreeting === 'function') {
        applyWelcomeGreeting();
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    const storedLang = localStorage.getItem('dmu_lang') || 'en';
    setLanguage(storedLang);
});

// Handle HTML5 Form Validation Translations
document.addEventListener('invalid', function (e) {
    const lang = localStorage.getItem('dmu_lang') || 'en';
    const el = e.target;

    if (lang === 'am') {
        if (el.tagName === 'SELECT') {
            el.setCustomValidity('እባክዎ ከዝርዝሩ ውስጥ ይምረጡ'); // Please select an item in the list
        } else if (el.validity.valueMissing) {
            el.setCustomValidity('እባክዎ ይህንን ቦታ ይሙሉ'); // Please fill out this field
        } else if (el.validity.typeMismatch) {
            if (el.type === 'email') {
                el.setCustomValidity('እባክዎ ትክክለኛ ኢሜል ያስገቡ'); // Please enter a valid email
            } else {
                el.setCustomValidity('እባክዎ ትክክለኛ መረጃ ያስገቡ'); // Please enter valid info
            }
        } else {
            el.setCustomValidity('እባክዎ ትክክለኛ መረጃ ያስገቡ'); // Please enter valid info
        }
    } else {
        // Clear custom validity so native English messages appear
        el.setCustomValidity('');
    }
}, true);

// Clear custom validity on input/change so form can re-evaluate
document.addEventListener('input', function (e) {
    if (e.target.setCustomValidity) {
        e.target.setCustomValidity('');
    }
}, true);

document.addEventListener('change', function (e) {
}, true);

// Custom Bilingual Confirm Modal
function showBilingualConfirm(messageEn, messageAm, onConfirmCallback) {
    // Check if modal already exists
    let modal = document.getElementById('bilingualConfirmModal');
    if (!modal) {
        const modalHtml = `
        <div id="bilingualConfirmModal" class="modal" style="display: none; z-index: 9999;">
            <div class="modal-content" style="max-width: 400px; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #ffc107;"></i>
                </div>
                <h3 data-en="Confirmation Required" data-am="ማረጋገጫ ያስፈልጋል">Confirmation Required</h3>
                <p id="bilingualConfirmMessage" style="margin: 20px 0; font-size: 16px;"></p>
                
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                    <button id="bilingualConfirmYes" class="btn-primary" style="background-color: #007bff; border: none; padding: 10px 20px; cursor: pointer;">
                        <i class="fas fa-check"></i> <span data-en="Yes, Proceed" data-am="አዎ፣ ቀጥል">Yes, Proceed</span>
                    </button>
                    <button id="bilingualConfirmNo" class="btn-secondary" style="background-color: #6c757d; border: none; padding: 10px 20px; cursor: pointer; color: white;">
                        <i class="fas fa-times"></i> <span data-en="Cancel" data-am="ሰርዝ">Cancel</span>
                    </button>
                </div>
            </div>
        </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('bilingualConfirmModal');
    }

    const lang = localStorage.getItem('dmu_lang') || 'en';
    const msgEl = document.getElementById('bilingualConfirmMessage');

    // Set message and languages
    msgEl.setAttribute('data-en', messageEn);
    msgEl.setAttribute('data-am', messageAm);
    msgEl.textContent = lang === 'am' ? messageAm : messageEn;

    // Update button text for current lang
    setLanguage(lang);

    // Show modal
    modal.style.display = 'flex';

    // Handle clicks
    const btnYes = document.getElementById('bilingualConfirmYes');
    const btnNo = document.getElementById('bilingualConfirmNo');

    // Clean up old event listeners to prevent multiple callbacks
    const newBtnYes = btnYes.cloneNode(true);
    const newBtnNo = btnNo.cloneNode(true);
    btnYes.parentNode.replaceChild(newBtnYes, btnYes);
    btnNo.parentNode.replaceChild(newBtnNo, btnNo);

    newBtnYes.addEventListener('click', () => {
        modal.style.display = 'none';
        if (typeof onConfirmCallback === 'function') {
            onConfirmCallback();
        }
    });

    newBtnNo.addEventListener('click', () => {
        modal.style.display = 'none';
    });
}

// Custom Bilingual Alert Modal (for simple notifications/warnings)
function showBilingualAlert(messageEn, messageAm) {
    let modal = document.getElementById('bilingualAlertModal');
    if (!modal) {
        const modalHtml = `
        <div id="bilingualAlertModal" class="modal" style="display: none; z-index: 9999;">
            <div class="modal-content" style="max-width: 400px; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <i class="fas fa-info-circle" style="font-size: 40px; color: #17a2b8;"></i>
                </div>
                <h3 data-en="Notice" data-am="ማሳሰቢያ">Notice</h3>
                <p id="bilingualAlertMessage" style="margin: 20px 0; font-size: 16px;"></p>
                
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                    <button id="bilingualAlertOk" class="btn-primary" style="background-color: #007bff; border: none; padding: 10px 30px; cursor: pointer;">
                        <i class="fas fa-check"></i> <span data-en="OK" data-am="እሺ">OK</span>
                    </button>
                </div>
            </div>
        </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('bilingualAlertModal');
    }

    const lang = localStorage.getItem('dmu_lang') || 'en';
    const msgEl = document.getElementById('bilingualAlertMessage');

    // Set message and languages
    msgEl.setAttribute('data-en', messageEn);
    msgEl.setAttribute('data-am', messageAm);
    msgEl.textContent = lang === 'am' ? messageAm : messageEn;

    // Update button text for current lang
    setLanguage(lang);

    // Show modal
    modal.style.display = 'flex';

    // Handle clicks
    const btnOk = document.getElementById('bilingualAlertOk');

    const newBtnOk = btnOk.cloneNode(true);
    btnOk.parentNode.replaceChild(newBtnOk, btnOk);

    newBtnOk.addEventListener('click', () => {
        modal.style.display = 'none';
    });
}
