// /RADS-TOOLING/assets/JS/customize.js
(() => {
    // ======= DOM =======
    const mediaBox = document.getElementById('mediaBox');
    const priceBox = document.getElementById('priceBox');
    const prodTitle = document.getElementById('prodTitle');

    const texList = document.getElementById('textures');
    const colList = document.getElementById('colors');
    const handleList = document.getElementById('handles');

    const stepPrev = document.getElementById('stepPrev');
    const stepNext = document.getElementById('stepNext');
    const stepLabel = document.getElementById('stepLabel');

    // sliders + number inputs
    const wSl = document.getElementById('wSlider');
    const hSl = document.getElementById('hSlider');
    const dSl = document.getElementById('dSlider');
    const wIn = document.getElementById('wInput');
    const hIn = document.getElementById('hInput');
    const dIn = document.getElementById('dInput');

    const unitSel = document.getElementById('unitSel');

    // ======= Constants / Paths =======
    const PID = Number(window.PID || 0);
    const MODEL_DIR = '/RADS-TOOLING/uploads/models/';
    const TEX_DIR = '/RADS-TOOLING/uploads/textures/';
    const HANDLE_DIR = '/RADS-TOOLING/uploads/handles/';

    const UNIT_TO_CM = { cm: 1, mm: 0.1, inch: 2.54, ft: 30.48, meter: 100 };

    const MATERIAL_MAP = {
        body: 'Material.009',
        door: 'Material_Door',
        interior: 'Material_Interior'
    };

    // --- precision helpers ---
    function decimalsOf(step) {
        const s = String(step);
        if (!s.includes('.')) return 0;
        return s.length - s.indexOf('.') - 1;
    }
    function roundToStep(v, step) {
        const p = Math.pow(10, decimalsOf(step));
        return Math.round((Number(v) / step) * p) / p * step;
    }
    function fmtUnit(v, unit, step = 1) {
        const d = Math.max(0, decimalsOf(step));
        const n = Number(v);
        if (!Number.isFinite(n)) return String(v);
        return n.toFixed(d).replace(/\.0+$/, '');
    }

    // ======= State =======
    let productData = null;
    let baseSize = { w: 80, h: 180, d: 45 }; // cm
    let activePart = 'body';
    let chosen = {
        size: { w: 80, h: 180, d: 45 }, // cm
        door: { textureId: null, colorId: null },
        body: { textureId: null, colorId: null },
        inside: { textureId: null, colorId: null },
        handle: { id: null }
    };

    // ======= Steps UI =======
    const STEPS = ['sec-size', 'sec-textures', 'sec-colors', 'sec-handles'];
    let stepIndex = 0;

    function syncStepUI() {
        STEPS.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) el.hidden = (i !== stepIndex);
        });
        const labels = ['Step 1 · Size', 'Step 2 · Textures', 'Step 3 · Colors', 'Step 4 · Handles'];
        if (stepLabel) stepLabel.textContent = labels[stepIndex] || 'Customize';

        if (stepPrev) stepPrev.disabled = stepIndex === 0;
        if (stepNext) stepNext.disabled = stepIndex === STEPS.length - 1;

        if (stepIndex === 1) { // Step 2 - Textures
            setTimeout(() => highlightPart('door', 1500), 300);
        } else if (stepIndex === 2) { // Step 3 - Colors
            setTimeout(() => highlightPart('door', 1500), 300);
        }
    }
    stepPrev?.addEventListener('click', () => { if (stepIndex > 0) { stepIndex--; syncStepUI(); } });
    stepNext?.addEventListener('click', () => { if (stepIndex < STEPS.length - 1) { stepIndex++; syncStepUI(); } });

    // ======= Boot =======
    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        if (!PID) return;
        productData = await loadProduct(PID);

        prodTitle && (prodTitle.textContent = productData.title || '—');

        // Viewer
        renderModel(productData.model_url);
        await waitForModelViewer();

        const mv = getMV();
        window.mv = mv;

        // ====== START: mesh-prefix -> dynamic material mapping (PASTE AFTER `await detectModelNodes(mv);`) ======
        /**
         * Configuration: what prefixes to look for (can change later)
         * Prefix matching is case-insensitive and checks the start of node.name
         */
        const MESH_PREFIXES = {
            body: 'Material.009',
            door: 'Material_Door',
            interior: 'Material_Interior',
        };

        /**
         * Build dynamicMaterialsByPart: { body: Set(materialName,..), door: Set(...), ... }
         * Uses the same traversal logic as detectModelNodes but collects node.materials when node name matches prefix.
         */
        function buildDynamicMaterialsByPart(mv) {
            const out = {};
            Object.keys(MESH_PREFIXES).forEach(k => out[k] = new Set());

            // helper walker (similar to detectModelNodes.walk)
            function walk(node) {
                if (!node) return;
                // check node name for prefix
                if (typeof node.name === 'string' && node.name.trim()) {
                    const n = node.name.trim().toLowerCase();
                    for (const [part, prefix] of Object.entries(MESH_PREFIXES)) {
                        if (prefix && n.startsWith(prefix.toLowerCase())) {
                            // collect materials referenced by this node (if any)
                            const mats = node.materials || node.material || node._materials || [];
                            if (Array.isArray(mats)) {
                                mats.forEach(m => {
                                    if (m && m.name) out[part].add(m.name);
                                });
                            } else if (mats && mats.name) {
                                out[part].add(mats.name);
                            }
                            break; // a node assigned to first-matching prefix
                        }
                    }
                }

                // traverse children (array or object)
                if (Array.isArray(node.children)) node.children.forEach(walk);
                else if (node.children && typeof node.children === 'object') Object.values(node.children).forEach(walk);
                if (node._children && Array.isArray(node._children)) node._children.forEach(walk);
            }

            // gather candidate arrays from model (same approach used by detectModelNodes)
            const symbols = Object.getOwnPropertySymbols(mv.model || {});
            const normalValues = Object.values(mv.model || {});
            const symbolValues = symbols.map(s => mv.model[s]);
            const allProps = [...symbolValues, ...normalValues];

            let nodeArray = null;
            for (const p of allProps) {
                if (!Array.isArray(p) || p.length === 0) continue;
                const first = p[0];
                if (!first) continue;
                if (Array.isArray(first.children) || first.children || first.materials || typeof first.name === 'string') {
                    nodeArray = p;
                    break;
                }
            }
            // if found a node array, walk it; else try fallback (some viewers expose scene or model.scene)
            if (nodeArray) {
                nodeArray.forEach(walk);
            } else {
                // fallback: try mv.model.scene or mv.model
                const root = (mv.model && mv.model.scene) ? mv.model.scene : mv.model;
                if (root) {
                    walk(root);
                    if (Array.isArray(root.children)) root.children.forEach(walk);
                }
            }

            // convert Sets -> arrays and expose globally for debugging
            const res = {};
            Object.entries(out).forEach(([k, s]) => res[k] = Array.from(s));
            window.dynamicMaterialsByPart = res;
            console.log('🔎 dynamicMaterialsByPart (built from mesh prefixes):', res);
            return res;
        }

        /**
         * Helper: get material objects for part (array). Prefer dynamicMaterialsByPart; fallback to MATERIAL_MAP single name.
         */
        function getMaterialsForPart(mv, part) {
            const dyn = window.dynamicMaterialsByPart || {};
            const names = (dyn && dyn[part] && dyn[part].length) ? dyn[part] : [];
            const mats = mv?.model?.materials || [];

            // return actual material objects (not just names)
            const found = [];
            if (names.length) {
                names.forEach(n => {
                    const m = mats.find(x => x.name === n);
                    if (m) found.push(m);
                });
            }

            // fallback: if nothing found via mesh -> try original MATERIAL_MAP lookup (single material name)
            if (!found.length) {
                const fallbackName = MATERIAL_MAP[part];
                if (fallbackName) {
                    const m = mats.find(x => x.name === fallbackName);
                    if (m) found.push(m);
                }
            }

            return found; // may be empty array
        }
        // build dynamic material map based on mesh prefixes (runs now and again on mv.load)
        buildDynamicMaterialsByPart(mv);
        mv?.addEventListener?.('load', () => buildDynamicMaterialsByPart(getMV()), { once: false });


        /**
         * Replace calls that used getMaterial(...) to use getMaterialsForPart(...) where we need to apply textures.
         * We'll not overwrite your existing functions here; instead we expose a helper to use inside applyTextureToPartEnhanced.
         * (Later in the file we will call buildDynamicMaterialsByPart(mv) once model is ready.)
         */
        // ====== END: mesh-prefix -> dynamic material mapping ======

        // Size
        applySizeConfs(productData.size_confs);
        initSizeReadouts();
        applyScaleFromControls();
        refreshPrice();

        // Unit switching
        unitSel?.addEventListener('change', () => {
            syncSlidersToUnit();
            applyScaleFromControls();
            refreshPrice();
            updateReadouts();
            updateUnitLabels();
            buildSizeGuide();
        });

        // Pair bindings (slider <-> input)
        bindPair(wSl, wIn);
        bindPair(hSl, hIn);
        bindPair(dSl, dIn);

        // Tabs
        bindPartTabs('#sec-textures .part-tabs');
        bindPartTabs('#sec-colors .part-tabs');

        // Size guide
        document.getElementById('btnSizeGuide')?.addEventListener('click', openSizeGuide);

        // Load initial colors for active tab
        const activeColorTab = document.querySelector('#sec-colors .part-tabs button.active');
        if (activeColorTab) {
            const part = activeColorTab.getAttribute('data-part');
            loadColorsForPart(part);
        }

        syncStepUI();
        updateUnitLabels();
        buildSizeGuide();
    }

    // ======= Data Loading =======
    async function loadProduct(id) {
        const url = `/RADS-TOOLING/backend/api/admin_products.php?action=view&id=${id}`;
        const r = await fetch(url, { credentials: 'same-origin' });
        if (!r.ok) throw new Error(`admin_products.php returned ${r.status}`);
        const js = await r.json();
        return await normalizeProduct(js);
    }

    async function normalizeProduct(js) {
        const d = (js?.data ?? js) || {};
        // model
        let model_url = '';
        if (typeof d.model_3d === 'string' && d.model_3d.trim()) {
            model_url = d.model_3d.startsWith('/RADS-TOOLING/') ? d.model_3d : (MODEL_DIR + d.model_3d.replace(/^\/+/, ''));
        } else if (typeof d.model_url === 'string' && d.model_url.trim()) {
            model_url = d.model_url.startsWith('/RADS-TOOLING/') ? d.model_url : (MODEL_DIR + d.model_url.replace(/^\/+/, ''));
        } else if (typeof d.model_path === 'string' && d.model_path.trim()) {
            model_url = MODEL_DIR + d.model_path.replace(/^\/+/, '');
        } else if (typeof d.model === 'string' && d.model.trim()) {
            model_url = MODEL_DIR + d.model.replace(/^\/+/, '');
        }
        if (model_url && !(await urlExists(model_url))) model_url = '';

        // size_confs
        let size_confs = Array.isArray(d.size_confs) ? d.size_confs : [];
        if (!size_confs.length && Array.isArray(d.size_config)) {
            size_confs = d.size_config.map(sc => ({
                dimension: (sc.dimension_type || '').toLowerCase(), // width|height|depth
                min: Number(sc.min_value || 0),
                max: Number(sc.max_value || 0),
                default: Number(sc.default_value || 0),
                step: Number(sc.step_value || 1),
                price_per_unit: Number(sc.price_per_unit || 0), // per sc.measurement_unit
                unit: sc.measurement_unit || 'cm',
                price_block_cm: Number(sc.price_block_cm || 0),     // e.g. 10 (cm per block)
                price_per_block: Number(sc.price_per_block || 0),   // e.g. 200 (₱ per block)
            }));
        }

        const allowed = d.allowed || {
            door: { textures: d.door_textures || [], colors: d.door_colors || [] },
            body: { textures: d.body_textures || [], colors: d.body_colors || [] },
            inside: { textures: d.inside_textures || [], colors: d.inside_colors || [] },
            handle: { handles: d.handles || [] }
        };

        const pricing = d.pricing || {
            base_price: parsePeso(d.base_price ?? d.price ?? 0),
            per_cm: { w: Number(d.price_per_cm_w || 0), h: Number(d.price_per_cm_h || 0), d: Number(d.price_per_cm_d || 0) },
            textures: d.texture_prices || {},
            colors: d.color_prices || {},
            handles: d.handle_prices || {}
        };

        return {
            title: d.title || d.name || d.product_name || 'Cabinet',
            model_url,
            size_confs,
            allowed,
            pricing
        };
    }

    async function urlExists(url) {
        try { const r = await fetch(url, { method: 'GET', cache: 'no-store' }); return r.ok; }
        catch { return false; }
    }
    function parsePeso(v) {
        if (typeof v === 'number') return v;
        if (typeof v !== 'string') return Number(v || 0);
        return Number(v.replace(/[₱,\s]/g, '')) || 0;
    }

    // ADD after line 150
    async function loadTexturesForPart(partName) {
        // prefer the top-level texList constant, fallback to DOM lookup
        const listEl = (typeof texList !== 'undefined' && texList) ? texList : document.getElementById('textures');
        if (!listEl) {
            console.warn('loadTexturesForPart: textures container not found for part:', partName);
            return;
        }

        listEl.innerHTML = '<div style="padding:10px;color:#666">Loading textures…</div>';

        try {
            // use PID (declared earlier) not PRODUCT_ID
            const resp = await fetch(`/RADS-TOOLING/backend/api/get_part_textures.php?product_id=${PID}&part=${encodeURIComponent(partName)}`, { credentials: 'same-origin' });
            const txt = await resp.text();

            let data;
            try {
                data = JSON.parse(txt);
            } catch (err) {
                console.error('get_part_textures returned non-JSON:', txt);
                listEl.innerHTML = '<div style="padding:10px;color:#e11">Error loading textures (invalid response)</div>';
                return;
            }

            if (!data || data.success !== true || !Array.isArray(data.textures)) {
                const msg = (data && data.message) ? data.message : 'No textures available';
                listEl.innerHTML = `<div style="padding:10px;color:#e11">${msg}</div>`;
                return;
            }

            // reuse your existing displayTextures() renderer
            displayTextures(data.textures, partName);

        } catch (err) {
            console.error('loadTexturesForPart failed:', err);
            listEl.innerHTML = '<div style="padding:10px;color:#e11">Error loading textures</div>';
        }
    }


    function displayTextures(textures, partName) {
        const texList = document.getElementById('textures');
        if (!texList) return;

        texList.innerHTML = '';

        if (textures.length === 0) {
            texList.innerHTML = '<div style="padding: 10px; color: #999;">No textures available</div>';
            return;
        }

        textures.forEach(tex => {
            const div = document.createElement('div');
            div.className = 'cz-swatch texture-option';
            div.dataset.textureId = tex.id;

            // Check if this texture is currently selected
            if (chosen[partName]?.mode === 'texture' && chosen[partName]?.id == tex.id) {
                div.classList.add('active');
            }

            div.innerHTML = `
            <img src="${tex.image_url}" 
                 alt="${tex.texture_name}"
                 title="${tex.texture_name} - ₱${tex.base_price || 0}"
                 style="width: 60px; height: 60px; object-fit: cover; cursor: pointer; border-radius: 4px; border: 2px solid transparent;">
            <span style="display: block; font-size: 10px; text-align: center; margin-top: 4px;">${tex.texture_name}</span>
        `;

            div.onclick = () => {
                // Remove active from others
                texList.querySelectorAll('.texture-option').forEach(opt => opt.classList.remove('active'));
                div.classList.add('active');

                // Apply texture
                applyTextureToPartEnhanced(partName, tex);
            };

            texList.appendChild(div);
        });
    }

    async function applyTextureToPartEnhanced(partName, texture) {
        const mv = getMV();
        if (!mv || !mv.model) return;

        try {
            // Get the correct material based on part
            const materialName = MATERIAL_MAP[partName];
            const material = getMaterial(mv, materialName);

            if (!material) {
                console.error(`Material not found for part: ${partName} (${materialName})`);

                // Fallback: try by index if Door1/Door2
                if (partName === 'door') {
                    // Apply to both doors if they exist
                    const door1 = getMaterial(mv, 'Door1');
                    const door2 = getMaterial(mv, 'Door2');

                    const tex = await mv.createTexture(texture.image_url);

                    if (door1) {
                        door1.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
                        door1.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
                    }
                    if (door2) {
                        door2.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
                        door2.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
                    }
                }

                // ADD THIS: Try THREE.js direct access as last resort
                console.log('⚠️ Trying THREE.js direct access...');
                const threeMat = getThreeMaterial(mv, materialName);
                if (threeMat) {
                    const loader = new THREE.TextureLoader();
                    loader.load(texture.image_url, (tex) => {
                        threeMat.map = tex;
                        threeMat.color.setRGB(1, 1, 1);
                        threeMat.needsUpdate = true;
                        console.log(`✅ Applied via THREE.js to "${materialName}"`);

                        chosen[partName] = {
                            mode: 'texture',
                            id: texture.id,
                            price: parseFloat(texture.base_price) || 0
                        };
                        refreshPrice();
                    });
                }

                return;
            }

            // Create and apply texture (original code)
            const tex = await mv.createTexture(texture.image_url);
            material.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
            material.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);

            // Store selection
            chosen[partName] = {
                mode: 'texture',
                id: texture.id,
                price: parseFloat(texture.base_price) || 0
            };

            refreshPrice();

        } catch (error) {
            console.error('Error applying texture:', error);
        }
    }

    async function loadColorsForPart(partName) {
        const colList = document.getElementById('colors');
        if (!colList) return;

        colList.innerHTML = '<div style="padding:10px;color:#666">Loading colors...</div>';

        try {
            const resp = await fetch(`/RADS-TOOLING/backend/api/get_part_colors.php?product_id=${PID}&part=${partName}`, { credentials: 'same-origin' });
            const data = await resp.json();

            if (!data.success || !data.colors || data.colors.length === 0) {
                colList.innerHTML = '<div style="padding:10px;color:#999">No colors available</div>';
                return;
            }

            displayColors(data.colors, partName);
        } catch (err) {
            console.error('Load colors failed:', err);
            colList.innerHTML = '<div style="padding:10px;color:#e11">Error loading colors</div>';
        }
    }

    function displayColors(colors, partName) {
        const colList = document.getElementById('colors');
        if (!colList) return;

        colList.innerHTML = '';

        colors.forEach(color => {
            const div = document.createElement('div');
            div.className = 'cz-item';

            const isActive = chosen[partName]?.mode === 'color' && chosen[partName]?.id == color.id;
            if (isActive) div.classList.add('is-active');

            div.innerHTML = `
            <div class="cz-swatch" style="background-color: ${color.hex_value || '#ccc'}; width: 96px; height: 96px;"></div>
            <div class="cz-item-name">${color.color_name}</div>
            ${color.base_price > 0 ? `<div class="cz-price-badge">+ ₱${color.base_price}</div>` : ''}
        `;

            div.onclick = () => {
                const wasActive = div.classList.contains('is-active');

                // Remove active from all
                colList.querySelectorAll('.cz-item').forEach(opt => opt.classList.remove('is-active'));

                if (wasActive) {
                    // DESELECT - reset to original texture/color
                    console.log(`🔄 Deselected color for ${partName}`);

                    // Reset material to white (shows original texture)
                    const mv = getMV();
                    const mat = getMaterial(mv, MATERIAL_MAP[partName]);
                    if (mat) {
                        mat.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
                    }

                    // Clear chosen state
                    chosen[partName] = { mode: null, id: null };
                    refreshPrice();
                } else {
                    // SELECT - apply color
                    div.classList.add('is-active');
                    applyColorToPart(partName, color.hex_value, color.id);
                }
            };

            colList.appendChild(div);
        });
    }

    // ======= Viewer =======
    function renderModel(modelURL) {
        mediaBox.innerHTML = `
      <model-viewer id="mv"
        style="width:100%;height:100%;display:block;--poster-color:transparent;background:#f7f7f7"
        src="${modelURL}"
        camera-controls auto-rotate bounds="tight" reveal="auto"
        environment-image="neutral" shadow-intensity="1" exposure="1">
      </model-viewer>`;
    }

    function getMV() {
        return document.getElementById('mv');
    }

    function waitForModelViewer() {
        return new Promise(res => {
            const mv = getMV();
            if (!mv) return res(null);
            if (mv?.model) return res(mv);
            mv.addEventListener('load', () => res(mv), { once: true });
        });
    }

    function getMaterial(mv, name) {
        const mats = mv?.model?.materials || [];
        return mats.find(m => m.name === name) || null;
    }

    function getThreeMaterial(mv, materialName) {
        if (!mv?.model?.scene) return null;

        let foundMat = null;

        mv.model.scene.traverse((child) => {
            if (child.isMesh && child.material) {
                const materials = Array.isArray(child.material) ? child.material : [child.material];

                materials.forEach((mat) => {
                    if (mat.name === materialName) {
                        foundMat = mat;
                    }
                });
            }
        });

        return foundMat;
    }

    function highlightPart(partKey) {
        const mv = getMV();
        if (!mv?.model?.materials) return;

        console.log(`📍 Highlighting ${partKey}`);

        const materialName = MATERIAL_MAP[partKey];
        const mat = getMaterial(mv, materialName);

        if (mat && mat.setEmissiveFactor) {
            // Store original emissive
            if (!mat._origEmissive) {
                mat._origEmissive = [0, 0, 0];
            }

            // Set glow
            mat.setEmissiveFactor([0.3, 0.5, 1.0]);

            // Auto-clear after 1 second
            setTimeout(() => {
                mat.setEmissiveFactor(mat._origEmissive || [0, 0, 0]);
                console.log(`✅ Cleared highlight for ${partKey}`);
            }, 1000);
        }
    }

    // ======= Size / Scale =======
    const DEF_DIM = { min: 40, max: 300, default: 80, step: 1, price_per_unit: 0, unit: 'cm' };
    function dimCfg(dim) {
        const it = (productData.size_confs || []).find(x => x.dimension === dim);
        return it ? { ...DEF_DIM, ...it } : { ...DEF_DIM };
    }

    function applySizeConfs() {
        const w = dimCfg('width'), h = dimCfg('height'), d = dimCfg('depth');

        // base defaults in cm
        baseSize = {
            w: w.default * (UNIT_TO_CM[w.unit] || 1),
            h: h.default * (UNIT_TO_CM[h.unit] || 1),
            d: d.default * (UNIT_TO_CM[d.unit] || 1)
        };

        // initial chosen = mins in cm
        chosen.size = {
            w: w.min * (UNIT_TO_CM[w.unit] || 1),
            h: h.min * (UNIT_TO_CM[h.unit] || 1),
            d: d.min * (UNIT_TO_CM[d.unit] || 1)
        };

        syncSlidersToUnit();
        updateReadouts();
    }

    function setPairFromCfg(sl, inp, cfg, targetUnit, valueInTarget) {
        if (!sl || !inp) return;
        const k = UNIT_TO_CM[cfg.unit] || 1;   // cfg.unit -> cm
        const t = UNIT_TO_CM[targetUnit] || 1; // cm -> target
        const toTarget = v => (v * k) / t;

        const min = toTarget(cfg.min);
        const max = toTarget(cfg.max);
        const step = toTarget(cfg.step);

        sl.min = String(min); sl.max = String(max); sl.step = String(step);
        inp.min = String(min); inp.max = String(max); inp.step = String(step);

        const v = Math.min(max, Math.max(min, roundToStep(valueInTarget, step)));
        sl.value = String(v);
        inp.value = fmtUnit(v, targetUnit, step);
    }

    function bindPair(sl, inp) {
        if (!sl || !inp) return;

        const mirror = (src) => {
            if (src === sl) {
                inp.value = fmtUnit(sl.value, unitSel?.value || 'cm', Number(inp.step) || 1);
            } else {
                const v = Number(inp.value);
                if (Number.isFinite(v)) sl.value = String(v);
            }
            applyScaleFromControls();
            refreshPrice();
            updateReadouts();
        };

        const clampAndSnap = () => {
            const min = Number(inp.min), max = Number(inp.max), step = Number(inp.step) || 1;
            let v = Number(inp.value);
            if (!Number.isFinite(v)) v = min;
            v = Math.min(max, Math.max(min, roundToStep(v, step)));
            sl.value = String(v);
            inp.value = fmtUnit(v, unitSel?.value || 'cm', step);
            applyScaleFromControls();
            refreshPrice();
            updateReadouts();
        };

        sl.addEventListener('input', () => mirror(sl));
        inp.addEventListener('input', () => mirror(inp));
        inp.addEventListener('change', clampAndSnap);
        inp.addEventListener('blur', clampAndSnap);
    }

    function syncSlidersToUnit() {
        const unit = unitSel?.value || 'cm';
        const t = UNIT_TO_CM[unit] || 1;
        setPairFromCfg(wSl, wIn, dimCfg('width'), unit, chosen.size.w / t);
        setPairFromCfg(hSl, hIn, dimCfg('height'), unit, chosen.size.h / t);
        setPairFromCfg(dSl, dIn, dimCfg('depth'), unit, chosen.size.d / t);
    }

    function applyScaleFromControls() {
        const unit = unitSel?.value || 'cm';
        const t = UNIT_TO_CM[unit] || 1;

        const w_cm = Number(wSl?.value || 0) * t;
        const h_cm = Number(hSl?.value || 0) * t;
        const d_cm = Number(dSl?.value || 0) * t;
        chosen.size = { w: w_cm, h: h_cm, d: d_cm };

        const mv = getMV();
        if (mv?.model) {
            const root = (mv.model && mv.model.scene) ? mv.model.scene : mv.model;
            const sx = w_cm / baseSize.w, sy = h_cm / baseSize.h, sz = d_cm / baseSize.d;
            if (root?.scale?.set) root.scale.set(sx, sy, sz);
        }
    }

    // ======= Readouts / Unit labels =======
    function initSizeReadouts() {
        makeReadout(wSl, 'wVal');
        makeReadout(hSl, 'hVal');
        makeReadout(dSl, 'dVal');
        updateReadouts();
    }
    function makeReadout(sl, id) {
        if (!sl) return;
        const span = document.createElement('span');
        span.id = id; span.className = 'cz-val';
        span.style.marginLeft = '8px'; span.style.fontWeight = '700';
        sl.parentElement?.appendChild(span);
    }
    function updateReadouts() {
        const unit = unitSel?.value || 'cm';
        const wOut = document.getElementById('wVal');
        const hOut = document.getElementById('hVal');
        const dOut = document.getElementById('dVal');

        const wStep = Number(wIn?.step || 1);
        const hStep = Number(hIn?.step || 1);
        const dStep = Number(dIn?.step || 1);

        if (wOut && wSl) wOut.textContent = `${fmtUnit(wSl.value, unit, wStep)} ${unit}`;
        if (hOut && hSl) hOut.textContent = `${fmtUnit(hSl.value, unit, hStep)} ${unit}`;
        if (dOut && dSl) dOut.textContent = `${fmtUnit(dSl.value, unit, dStep)} ${unit}`;
    }
    function updateUnitLabels() {
        const unit = unitSel?.value || 'cm';
        document.querySelectorAll('.cz-unit-label').forEach(sp => sp.textContent = unit);
    }

    // ======= Tabs / Active Part =======
    function bindPartTabs(sel) {
        const tabs = document.querySelector(sel);
        if (!tabs) return;

        // Load textures for initial active tab
        const activeBtn = tabs.querySelector('button.active');
        if (activeBtn && sel.includes('textures')) {
            const initialPart = activeBtn.getAttribute('data-part');
            loadTexturesForPart(initialPart);
        }

        tabs.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-part]');
            if (!btn) return;

            tabs.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const partName = btn.getAttribute('data-part');
            setActivePart(partName);

            // Load textures for this part if in texture section
            if (sel.includes('textures')) {
                loadTexturesForPart(partName);
            }

            if (sel.includes('colors')) {
                loadColorsForPart(partName);
            }

            // Highlight the 3D part
            highlightPart(partName);
        });
    }
    function setActivePart(k) { activePart = k; highlightPart(k); }

    // ======= Apply Texture / Color =======
    async function applyTextureToPart(partKey, url, id) {
        const mv = getMV(); if (!mv) return;
        try {
            const tex = await mv.createTexture(url);
            const mat = getMaterial(mv, MATERIAL_MAP[partKey]); if (!mat) return;
            mat.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
            mat.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
            setChosen(partKey, { mode: 'texture', id });
            refreshPrice();
        } catch (err) { console.error('Texture apply failed:', err); }
    }

    function applyColorToPart(partKey, hex, id) {
        const mv = getMV();
        if (!mv) return;

        const mat = getMaterial(mv, MATERIAL_MAP[partKey]);
        if (!mat) return;

        // DON'T remove texture - comment out this line:
        // mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);

        // Just set color factor (tints the texture)
        const [r, g, b, a] = hexToRGBA01(hex);
        mat.pbrMetallicRoughness.setBaseColorFactor([r, g, b, a]);

        setChosen(partKey, { mode: 'color', id });
        refreshPrice();
    }

    function setChosen(partKey, data) {
        if (partKey === 'handle') {
            chosen.handle.id = data?.id ?? null;
            return;
        }

        // Store texture and color separately
        if (data?.mode === 'texture') {
            chosen[partKey].textureId = data?.id ?? null;
        } else if (data?.mode === 'color') {
            chosen[partKey].colorId = data?.id ?? null;
        }
    }

    function clearChosen(partKey) {
        if (partKey === 'handle') {
            chosen.handle.id = null;
        } else {
            chosen[partKey].textureId = null;
            chosen[partKey].colorId = null;

            // Reset 3D model appearance
            const mv = getMV();
            if (mv) {
                const materials = getMaterialsForPart(partKey);
                materials.forEach(mat => {
                    if (mat && mat.pbrMetallicRoughness) {
                        // Clear texture
                        if (mat.pbrMetallicRoughness.baseColorTexture) {
                            mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);
                        }
                        // Reset to default color
                        if (mat.pbrMetallicRoughness.setBaseColorFactor) {
                            mat.pbrMetallicRoughness.setBaseColorFactor([0.8, 0.8, 0.8, 1]);
                        }
                    }
                });
            }
        }
        refreshPrice();
    }

    function hexToRGBA01(hex) {
        const m = hex.replace('#', '');
        const v = parseInt(m, 16);
        const r = (v >> 16) & 255;
        const g = (v >> 8) & 255;
        const b = v & 255;
        return [r / 255, g / 255, b / 255, 1];
    }

    // ======= Build Options (Allowed) =======
    function rebuildTextureOptions() {
        if (!texList) return; texList.innerHTML = '';
        const allow = productData?.allowed?.[getActiveTabPart('#sec-textures .part-tabs')] || { textures: [] };
        (allow.textures || []).forEach(t => {
            const btn = document.createElement('button');
            btn.className = 'cz-item';
            const surcharge = getSurcharge('textures', t.id);
            // NEW: Improved layout matching color section
            btn.innerHTML = `
        <div class="cz-swatch cz-swatch-texture">
            <img src="${TEX_DIR + t.file}" alt="${t.name || 'texture'}" style="width:100%;height:100%;object-fit:cover;">
        </div>
        <div class="cz-item-name">${t.name || 'Texture'}</div>
        ${priceBadge(surcharge)}
    `;
            // NEW: Click to select, click again to deselect
            btn.addEventListener('click', () => {
                if (btn.classList.contains('is-active')) {
                    // Deselect - remove texture
                    btn.classList.remove('is-active');
                    chosen[activePart].textureId = null;

                    // Clear texture from 3D model
                    const mv = getMV();
                    if (mv) {
                        const materials = getMaterialsForPart(activePart);
                        materials.forEach(mat => {
                            if (mat && mat.pbrMetallicRoughness && mat.pbrMetallicRoughness.baseColorTexture) {
                                mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);
                            }
                        });
                    }
                    refreshPrice();
                } else {
                    // Select - apply texture
                    applyTextureToPart(activePart, TEX_DIR + t.file, t.id);
                    selectExclusive(texList, btn);
                }
            });
            texList.appendChild(btn);
        });
    }

    async function rebuildColorOptions() {
        if (!colList) return;
        colList.innerHTML = '';

        // first try the productData.allowed (fast, preferred)
        const partKey = getActiveTabPart('#sec-colors .part-tabs') || 'door';
        const allowFromProduct = productData?.allowed?.[partKey] || null;

        let colorsToRender = [];

        if (allowFromProduct && Array.isArray(allowFromProduct.colors) && allowFromProduct.colors.length) {
            colorsToRender = allowFromProduct.colors.slice();
        } else {
            // fallback: call backend list_colors for this product (admin_customization.php?action=list_colors&product_id=PID)
            try {
                if (!PID) throw new Error('PID missing');
                const resp = await fetch(`/RADS-TOOLING/backend/api/admin_customization.php?action=list_colors&product_id=${PID}`, { credentials: 'same-origin' });
                if (resp.ok) {
                    const js = await resp.json();
                    if (js?.success && Array.isArray(js.data)) {
                        // js.data items include 'assigned' (1/0) and hex fields (we normalized in backend)
                        // map to the shape used elsewhere: { id, name, hex, assigned, allowed_parts? }
                        colorsToRender = js.data
                            .filter(c => Number(c.assigned || 0) === 1) // only assigned colors for this product
                            .map(c => ({
                                id: Number(c.id),
                                name: c.color_name || c.name || c.hex_value || c.hex || '',
                                hex: (c.hex_value || c.hex || c.color_code || '').replace(/^([^#])/, '#$1'),
                                allowed_parts: c.allowed_parts || c.allowed || null // optional
                            }));
                    }
                } else {
                    console.warn('list_colors returned', resp.status);
                }
            } catch (err) {
                console.warn('Could not fetch product colors fallback:', err);
            }
        }

        // If the colors have allowed_parts info, filter by active part
        const activePart = partKey; // 'door' | 'body' | 'inside' etc.
        const filtered = colorsToRender.filter(c => {
            if (!c) return false;
            // if allowed_parts is an array, require it contains activePart (support synonyms)
            if (Array.isArray(c.allowed_parts) && c.allowed_parts.length) {
                // normalize keys (some admin code may use 'interior' vs 'inside')
                const norms = c.allowed_parts.map(x => String(x || '').toLowerCase());
                const map = { inside: 'interior', interior: 'interior', door: 'door', body: 'body' };
                const dbPart = map[activePart] || activePart;
                return norms.includes(dbPart) || norms.includes(activePart);
            }
            // otherwise, no per-part restriction -> show
            return true;
        });

        (filtered || []).forEach(c => {
            const btn = document.createElement('button');
            btn.className = 'cz-item';
            const hex = (c.hex || '#cccccc');
            btn.innerHTML = `
            <div class="cz-swatch" style="background:${hex}"></div>
            <div class="cz-item-name">${c.name || hex}</div>
            ${priceBadge(getSurcharge('colors', c.id))}
        `;
            btn.addEventListener('click', () => {
                if (btn.classList.contains('is-active')) {
                    // Deselect - remove color and reduce price
                    btn.classList.remove('is-active');
                    clearChosen(activePart);
                } else {
                    // Select - apply color
                    applyColorToPart(activePart, hex, c.id);
                    selectExclusive(colList, btn);
                }
            });
            colList.appendChild(btn);
        });

        // If nothing to render, show a placeholder
        if ((filtered || []).length === 0) {
            const p = document.createElement('div');
            p.className = 'cz-empty';
            p.textContent = 'No colors available';
            colList.appendChild(p);
        }
    }

    async function rebuildHandleOptions() {
        if (!handleList) return;
        handleList.innerHTML = '';

        // prefer productData.allowed.handle if present
        const allowHandles = productData?.allowed?.handle || null;
        let handlesToRender = [];

        if (allowHandles && Array.isArray(allowHandles.handles) && allowHandles.handles.length) {
            handlesToRender = allowHandles.handles.slice();
        } else {
            // fallback: fetch handles list and only include those assigned to product (if product assignment exists)
            try {
                if (!PID) throw new Error('PID missing');
                const resp = await fetch(`/RADS-TOOLING/backend/api/admin_customization.php?action=list_handles`, { credentials: 'same-origin' });
                if (resp.ok) {
                    const js = await resp.json();
                    if (js?.success && Array.isArray(js.data)) {
                        // map to expected shape { id, name, preview/file, assigned?, allowed_parts? }
                        handlesToRender = js.data.map(h => ({
                            id: Number(h.id),
                            name: h.handle_name || h.name || '',
                            preview: h.handle_image || h.file || '',
                            allowed_parts: h.allowed_parts || h.allowed || null,
                            assigned: h.assigned || 0
                        }));
                        // if you want only product-assigned handles, try to detect assigned via productData or backend later
                        // If admin assigns handles via product_handles table you'll want admin_customization.php?action=list_handles&product_id=PID to return assigned.
                    }
                } else {
                    console.warn('list_handles returned', resp.status);
                }
            } catch (err) {
                console.warn('Could not fetch handles fallback:', err);
            }
        }

        // If handles include allowed_parts, filter by part (handles usually only apply to doors)
        const activePart = getActiveTabPart('#sec-handles .part-tabs') || 'door';
        const filtered = handlesToRender.filter(h => {
            if (!h) return false;
            if (Array.isArray(h.allowed_parts) && h.allowed_parts.length) {
                const norms = h.allowed_parts.map(x => String(x || '').toLowerCase());
                return norms.includes(activePart) || norms.includes('door');
            }
            return true;
        });

        (filtered || []).forEach(h => {
            const btn = document.createElement('button');
            btn.className = 'cz-item';
            const src = HANDLE_DIR + (h.preview || '');
            btn.innerHTML = `
            <div class="cz-swatch"><img src="${src}" alt="${h.name || 'Handle'}" onerror="this.style.opacity=.25"></div>
            <div class="cz-item-name">${h.name || 'Handle'}</div>
            ${priceBadge(getSurcharge('handles', h.id))}
        `;
            btn.addEventListener('click', () => {
                if (btn.classList.contains('is-active')) {
                    // Deselect - remove handle and reduce price
                    btn.classList.remove('is-active');
                    chosen.handle.id = null;
                } else {
                    // Select - apply handle
                    chosen.handle.id = h.id;
                    selectExclusive(handleList, btn);
                }
                refreshPrice();
            });
            handleList.appendChild(btn);
        });

        if ((filtered || []).length === 0) {
            const p = document.createElement('div');
            p.className = 'cz-empty';
            p.textContent = 'No handles available';
            handleList.appendChild(p);
        }
    }
    function selectExclusive(listEl, btn) {
        listEl.querySelectorAll('.cz-item').forEach(x => x.classList.remove('is-active'));
        btn.classList.add('is-active');
    }

    function getActiveTabPart(selector) {
        const tabs = document.querySelector(selector);
        const active = tabs?.querySelector('button.active')?.getAttribute('data-part');
        return active || 'door';
    }

    function priceBadge(surcharge) {
        if (!Number.isFinite(surcharge) || surcharge === 0) return '';
        // Always show as addition (positive)
        const abs = Math.abs(surcharge);
        return `<div class="cz-price-badge">+ ₱${fmt(abs)}</div>`;
    }

    // ======= Pricing =======
    function refreshPrice() {
        const p = computePrice();
        if (priceBox) priceBox.textContent = '₱ ' + fmt(p);
    }

    function computePrice() {
        let total = Number(productData?.pricing?.base_price || 0);

        // Baseline is ADMIN MIN
        const wCfg = dimCfg('width'), hCfg = dimCfg('height'), dCfg = dimCfg('depth');
        const baseW = wCfg.min * (UNIT_TO_CM[wCfg.unit] || 1);
        const baseH = hCfg.min * (UNIT_TO_CM[hCfg.unit] || 1);
        const baseD = dCfg.min * (UNIT_TO_CM[dCfg.unit] || 1);

        total += dimSurcharge(chosen.size.w, baseW, wCfg);
        total += dimSurcharge(chosen.size.h, baseH, hCfg);
        total += dimSurcharge(chosen.size.d, baseD, dCfg);

        // FIXED: Add ALL active options per part (texture AND color if both exist)
        ['door', 'body', 'inside'].forEach(part => {
            const partData = chosen[part];

            // Add texture price if texture is set
            if (partData.textureId) {
                total += getSurcharge('textures', partData.textureId);
            }

            // Add color price if color is set
            if (partData.colorId) {
                total += getSurcharge('colors', partData.colorId);
            }
        });

        // Handle price
        if (chosen.handle.id) {
            total += getSurcharge('handles', chosen.handle.id);
        }

        return Math.max(0, Math.round(total));
    }

    // Prefer block pricing if provided; else per-unit
    function dimSurcharge(sizeCm, baseCm, cfg) {
        const over = Math.max(0, sizeCm - baseCm);
        if (over <= 0) return 0;

        const blockCm = Number(cfg.price_block_cm || 0);
        const pricePerBlock = Number(cfg.price_per_block || 0);

        if (blockCm > 0 && pricePerBlock > 0) {
            // “every 10 cm add ₱X” → charge per full block
            const blocks = Math.floor(over / blockCm);
            return blocks * pricePerBlock;
        }

        // fallback: per-unit (cfg.unit) → convert to ₱/cm
        const perUnit = Number(cfg.price_per_unit || 0);
        const perCm = perUnit / (UNIT_TO_CM[cfg.unit] || 1);
        return over * perCm;
    }

    function getSurcharge(kind, id) {
        const map = (productData?.pricing?.[kind]) || {};
        const key = String(id);
        const val = map[key] ?? map[id] ?? 0;
        return Number(val || 0);
    }
    function fmt(n) { return Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 }); }

    // ======= Size Guide (modal) =======
    function openSizeGuide() {
        const dlg = ensureSizeGuideHost();
        buildSizeGuide();
        dlg.showModal?.();
    }
    function ensureSizeGuideHost() {
        let dlg = document.getElementById('sizeGuide');
        if (dlg) return dlg;
        dlg = document.createElement('dialog');
        dlg.id = 'sizeGuide';
        dlg.style.padding = '0';
        dlg.style.border = '0';
        dlg.style.borderRadius = '12px';
        dlg.style.maxWidth = '560px';
        dlg.style.margin = 'auto';
        dlg.style.width = '92vw';
        dlg.style.boxShadow = '0 12px 32px rgba(15,23,42,.18)';
        dlg.innerHTML = `
      <div style="padding:18px 18px 8px;border-bottom:1px solid #eef2f7;font-weight:700">Size guide</div>
      <div id="sizeGuideBody" style="padding:16px"></div>
      <div style="display:flex;justify-content:flex-end;padding:12px 16px;border-top:1px solid #eef2f7">
        <button id="sizeGuideClose" class="btn">Close</button>
      </div>`;
        document.body.appendChild(dlg);
        dlg.querySelector('#sizeGuideClose').addEventListener('click', () => dlg.close?.());
        return dlg;
    }
    function buildSizeGuide() {
        const body = document.getElementById('sizeGuideBody'); if (!body) return;
        const unit = unitSel?.value || 'cm';
        const t = UNIT_TO_CM[unit] || 1;

        const dims = ['width', 'height', 'depth'].map(dim => {
            const c = dimCfg(dim);
            const k = UNIT_TO_CM[c.unit] || 1;
            const toTarget = v => (v * k) / t;
            return {
                dim,
                range: `${fmtUnit(toTarget(c.min), unit)} – ${fmtUnit(toTarget(c.max), unit)} ${unit}`,
                price:
                    (Number(c.price_block_cm) > 0 && Number(c.price_per_block) > 0)
                        ? `₱ ${fmt(c.price_per_block)} / every ${fmtUnit((UNIT_TO_CM[c.unit] || 1) ? (c.price_block_cm / (UNIT_TO_CM['cm'] || 1)) : c.price_block_cm, 'cm')} cm`
                        : `₱ ${fmt(c.price_per_unit || 0)} / ${c.unit}`
            };
        });

        // current value in cm -> conversions
        const cur = {
            width_cm: Number(wSl?.value || 0) * (UNIT_TO_CM[unit] || 1),
            height_cm: Number(hSl?.value || 0) * (UNIT_TO_CM[unit] || 1),
            depth_cm: Number(dSl?.value || 0) * (UNIT_TO_CM[unit] || 1)
        };
        const U = ['mm', 'cm', 'inch', 'ft', 'meter'];
        const conv = (cm, u) => {
            const to = { mm: 10, cm: 1, inch: 1 / 2.54, ft: 1 / 30.48, meter: 1 / 100 }[u];
            return fmtUnit(cm * to, u, 0.01);
        };

        const rowsMain = dims.map(d => `
      <tr>
        <td style="padding:6px 10px;font-weight:600;text-transform:capitalize">${d.dim}</td>
        <td style="padding:6px 10px">${d.range}</td>
        <td style="padding:6px 10px">${d.price}</td>
      </tr>`).join('');

        const rowsConv = `
      <tr><th></th>${U.map(u => `<th>${u}</th>`).join('')}</tr>
      <tr>
        <td style="text-transform:capitalize">width</td>
        ${U.map(u => `<td>${conv(cur.width_cm, u)}</td>`).join('')}
      </tr>
      <tr>
        <td style="text-transform:capitalize">height</td>
        ${U.map(u => `<td>${conv(cur.height_cm, u)}</td>`).join('')}
      </tr>
      <tr>
        <td style="text-transform:capitalize">depth</td>
        ${U.map(u => `<td>${conv(cur.depth_cm, u)}</td>`).join('')}
      </tr>
    `;

        body.innerHTML = `
      <div style="color:#6b7280;font-size:13px;margin-bottom:8px">
        Ranges and increments are controlled by the admin for each product.
      </div>

      <table style="width:100%;border-collapse:collapse;margin-bottom:14px">
        <thead>
          <tr style="background:#f8fafc">
            <th style="text-align:left;padding:8px 10px">Dimension</th>
            <th style="text-align:left;padding:8px 10px">Range</th>
            <th style="text-align:left;padding:8px 10px">Price per unit</th>
          </tr>
        </thead>
        <tbody>${rowsMain}</tbody>
      </table>

      <div style="font-weight:600;margin:10px 0 6px">Current selection (converted)</div>
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#f8fafc">
            <th style="text-align:left;padding:8px 10px"></th>
            ${U.map(u => `<th style="text-align:left;padding:8px 10px">${u}</th>`).join('')}
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:6px 10px;text-transform:capitalize">width</td>
            ${U.map(u => `<td style="padding:6px 10px">${conv(cur.width_cm, u)}</td>`).join('')}
          </tr>
          <tr>
            <td style="padding:6px 10px;text-transform:capitalize">height</td>
            ${U.map(u => `<td style="padding:6px 10px">${conv(cur.height_cm, u)}</td>`).join('')}
          </tr>
          <tr>
            <td style="padding:6px 10px;text-transform:capitalize">depth</td>
            ${U.map(u => `<td style="padding:6px 10px">${conv(cur.depth_cm, u)}</td>`).join('')}
          </tr>
        </tbody>
      </table>
    `;
    }

    // ======= Helpers =======
    function getActiveTabPart(selector) {
        const tabs = document.querySelector(selector);
        return tabs?.querySelector('button.active')?.getAttribute('data-part') || 'door';
    }

})();