/**

 * Stock Request UI — scope bar for stationery / maid request pages

 */

(function (global) {

    'use strict';



    const CO_LABELS = { PTA: 'PTA', PT4: 'PT4', PTE: 'PTE' };

    const TYPE_META = {

        stationery: { label: 'เครื่องเขียน', icon: '📎', cls: 'type-stationery' },

        maid: { label: 'แม่บ้าน', icon: '🧹', cls: 'type-maid' }

    };

    const STORAGE_KEY = 'ptc_stock_scope_v1';



    let pageType = 'stationery';

    let session = null;

    let canPickCompany = false;

    let activeCompany = 'PTA';

    let onCompanyChange = null;

    let applyCompanyThemeFn = null;

    let hasCartItemsFn = null;



    function saveScope(company) {

        try {

            localStorage.setItem(STORAGE_KEY, JSON.stringify({ company, type: pageType }));

        } catch (e) { /* ignore */ }

    }



    function loadScope() {

        try {

            const raw = localStorage.getItem(STORAGE_KEY);

            return raw ? JSON.parse(raw) : null;

        } catch (e) {

            return null;

        }

    }



    function getCompany() {

        return activeCompany || session?.company || 'PTA';

    }



    function getPageType() {

        return pageType;

    }



    function updateScopeUI() {

        document.querySelectorAll('.stock-scope-pill[data-co]').forEach(btn => {

            const co = btn.dataset.co;

            btn.classList.toggle('is-active', co === activeCompany);

            btn.classList.toggle('is-locked', !canPickCompany && co !== activeCompany);

            btn.disabled = !canPickCompany && co !== activeCompany;

        });

        document.querySelectorAll('.stock-scope-pill[data-type]').forEach(btn => {

            btn.classList.toggle('is-active', btn.dataset.type === pageType);

        });

    }



    function applyCompanyTheme(co) {

        if (typeof applyCompanyThemeFn === 'function') applyCompanyThemeFn(co);

    }



    async function pickCompany(co) {

        if (!canPickCompany || activeCompany === co) return;

        if (typeof hasCartItemsFn === 'function' && hasCartItemsFn()) {

            const result = await global.Swal.fire({

                title: 'เปลี่ยนบริษัท?',

                text: 'ตะกร้าจะถูกล้างเมื่อเปลี่ยนบริษัท',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'เปลี่ยน',

                cancelButtonText: 'ยกเลิก'

            });

            if (!result.isConfirmed) return;

        }

        activeCompany = co;

        saveScope(co);

        applyCompanyTheme(co);

        updateScopeUI();

        if (typeof onCompanyChange === 'function') onCompanyChange(true);

    }



    function mountScopeBar() {

        const topbar = document.querySelector('.sc-req-topbar');

        if (!topbar || document.getElementById('stock-scope-bar')) return;



        const typeMeta = TYPE_META[pageType] || TYPE_META.stationery;

        const bar = document.createElement('div');

        bar.id = 'stock-scope-bar';

        bar.className = 'stock-scope-bar stock-req-scope-bar req-scope-v2';

        bar.innerHTML = `

            <div class="req-scope-main">

                <div class="stock-scope-pills" id="scope-co-pills">

                    <button type="button" class="stock-scope-pill co-PTA" data-co="PTA" onclick="StockRequestUI.pickCompany('PTA')">PTA</button>

                    <button type="button" class="stock-scope-pill co-PT4" data-co="PT4" onclick="StockRequestUI.pickCompany('PT4')">PT4</button>

                    <button type="button" class="stock-scope-pill co-PTE" data-co="PTE" onclick="StockRequestUI.pickCompany('PTE')">PTE</button>

                </div>

                <span class="req-type-badge ${typeMeta.cls}">${typeMeta.icon} ${typeMeta.label}</span>

            </div>

            <div class="req-quick-links">

                <a href="my_stock_history.html" class="req-quick-link"><i class="fas fa-clock-rotate-left"></i><span>ประวัติ</span></a>

                <button type="button" class="req-quick-link" onclick="showFlowGuide()"><i class="fas fa-circle-question"></i><span>คู่มือ</span></button>

                <a href="stock_management.html" class="req-quick-link hidden" id="req-approver-link"><i class="fas fa-user-check"></i><span>อนุมัติ</span></a>

            </div>

        `;

        topbar.insertAdjacentElement('afterend', bar);

    }



    function initScope(sess) {

        session = sess || {};

        canPickCompany = session.role === 'admin';

        const saved = loadScope();

        if (canPickCompany) {

            activeCompany = (saved?.company && saved.company !== 'All') ? saved.company : (session.company || 'PTA');

        } else if (CO_LABELS[session.company]) {

            activeCompany = session.company;

        } else {

            activeCompany = 'PTA';

        }

        saveScope(activeCompany);

        applyCompanyTheme(activeCompany);

        updateScopeUI();

    }



    function init(options) {

        pageType = options.pageType || 'stationery';

        onCompanyChange = options.onCompanyChange || null;

        applyCompanyThemeFn = options.applyCompanyTheme || null;

        hasCartItemsFn = options.hasCartItems || null;

        mountScopeBar();

        initScope(options.session);

    }



    global.StockRequestUI = {

        init,

        initScope,

        getCompany,

        getPageType,

        pickCompany,

        updateScopeUI

    };

})(window);

