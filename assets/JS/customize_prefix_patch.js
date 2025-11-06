// customize_prefix_patch.js
// Put this file BEFORE customize.js in the page so the helpers are available.

(function () {
    // configuration: prefix -> partKey
    const MESH_PREFIXES = {
        body: 'body',
        door: 'door',
        interior: 'interior',
        handle: 'handle'
    };

    // internal store
    const dynamic = {
        // partKey -> { materialNames: Set, materialObjects: Set, meshNames: Set }
    };
    Object.keys(MESH_PREFIXES).forEach(k => dynamic[k] = { names: new Set(), mats: new Set(), meshes: new Set() });

    // walker: given a root node, traverse and collect node.name and node.material(s)
    function walkNodes(root, cb) {
        if (!root) return;
        const children = root.children || root._children || [];
        if (Array.isArray(children)) {
            children.forEach(ch => {
                cb(ch);
                walkNodes(ch, cb);
            });
        } else if (typeof children === 'object') {
            Object.values(children).forEach(ch => {
                cb(ch);
                walkNodes(ch, cb);
            });
        }
    }

    // Build dynamic maps from a model-viewer instance
    function buildDynamicMaps(mv) {
        // reset
        Object.keys(dynamic).forEach(k => {
            dynamic[k].names = new Set();
            dynamic[k].mats = new Set();
            dynamic[k].meshes = new Set();
        });

        if (!mv || !mv.model) {
            console.debug('customize_prefix_patch: model-viewer not ready');
            return dynamic;
        }

        // try to find a node array like detectModelNodes in customize.js
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

        function processNode(n) {
            if (!n || !n.name) return;
            const name = String(n.name || '').trim();
            if (!name) return;

            // detect prefix
            const lname = name.toLowerCase();
            for (const [partKey, prefix] of Object.entries(MESH_PREFIXES)) {
                if (!prefix) continue;
                if (lname.startsWith(prefix.toLowerCase())) {
                    dynamic[partKey].meshes.add(name);

                    // collect materials (may be array or single)
                    const mats = n.materials || n.material || n._materials || [];
                    if (Array.isArray(mats)) {
                        mats.forEach(m => m && m.name && dynamic[partKey].names.add(m.name));
                    } else if (mats && mats.name) {
                        dynamic[partKey].names.add(mats.name);
                    }
                    break;
                }
            }
        }

        if (nodeArray) {
            nodeArray.forEach(processNode);
        } else {
            // fallback: traverse scene root if available
            const root = mv.model && mv.model.scene ? mv.model.scene : mv.model;
            if (root) {
                walkNodes(root, processNode);
            }
        }

        // convert names -> actual material objects from mv.model.materials
        const matsList = mv.model && mv.model.materials ? mv.model.materials : [];
        Object.keys(dynamic).forEach(k => {
            dynamic[k].mats = new Set();
            dynamic[k].names.forEach(nm => {
                const found = matsList.find(x => x && x.name === nm);
                if (found) dynamic[k].mats.add(found);
            });
        });

        // debug log
        const out = {};
        Object.keys(dynamic).forEach(k => {
            out[k] = {
                meshNames: Array.from(dynamic[k].meshes),
                materialNames: Array.from(dynamic[k].names),
                materialObjectsCount: dynamic[k].mats.size
            };
        });
        console.debug('customize_prefix_patch: built dynamic maps', out);

        // expose a snapshot for devs
        window.__dynamicMaterialMap = out;

        return dynamic;
    }

    // helper: return array of material objects for a part (may be empty array)
    function getMaterialsForPart(partKey) {
        // Returns array of materials from detected meshes
        const meshes = meshGroupsByPart[partKey] || [];
        const materials = [];

        meshes.forEach(mesh => {
            if (mesh.material) {
                // Single material
                if (!materials.includes(mesh.material)) {
                    materials.push(mesh.material);
                }
            } else if (mesh.materials && Array.isArray(mesh.materials)) {
                // Multiple materials
                mesh.materials.forEach(mat => {
                    if (!materials.includes(mat)) {
                        materials.push(mat);
                    }
                });
            }
        });

        return materials;
    }

    // helper: return first material object (or null)
    function getFirstMaterialObjectForPart(mv, partKey, fallbackName) {
        const arr = getMaterialsForPart(mv, partKey, fallbackName);
        return (arr && arr.length) ? arr[0] : null;
    }

    // helper: apply a texture URL to a list of material objects (robust)
    async function applyTextureToMaterials(mv, materialObjects, url) {
        if (!mv || !mv.createTexture) throw new Error('model-viewer not ready');
        const tex = await mv.createTexture(url);
        for (const mat of materialObjects) {
            try {
                if (!mat) continue;
                // modern API
                if (mat.pbrMetallicRoughness && mat.pbrMetallicRoughness.baseColorTexture && typeof mat.pbrMetallicRoughness.baseColorTexture.setTexture === 'function') {
                    mat.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
                    if (typeof mat.pbrMetallicRoughness.setBaseColorFactor === 'function') {
                        mat.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
                    }
                } else if (typeof mat.setBaseColorFactor === 'function') {
                    // older shape
                    mat.setBaseColorFactor([1, 1, 1, 1]);
                    if (mat.baseColorTexture && typeof mat.baseColorTexture.setTexture === 'function') {
                        mat.baseColorTexture.setTexture(tex);
                    }
                } else {
                    // try naive property
                    if (mat.baseColorTexture && typeof mat.baseColorTexture.setTexture === 'function') {
                        mat.baseColorTexture.setTexture(tex);
                    }
                }
            } catch (e) {
                console.warn('customize_prefix_patch: failed to apply texture to material', e, mat);
            }
        }
    }

    // attach to window for customize.js to use
    window.buildDynamicMaterialsByPart = function (mv) {
        try { return buildDynamicMaps(mv); } catch (e) { console.error('buildDynamicMaterialsByPart error', e); return dynamic; }
    };
    window.getMaterialsForPart = function (mv, partKey, fallbackName) { return getMaterialsForPart(mv, partKey, fallbackName); };
    window.getFirstMaterialObjectForPart = function (mv, partKey, fallbackName) { return getFirstMaterialObjectForPart(mv, partKey, fallbackName); };
    window.applyTextureToMaterials = async function (mv, materialObjects, url) { return applyTextureToMaterials(mv, materialObjects, url); };

    // auto-run on any model-viewer in the page
    function hookAllModelViewers() {
        const list = document.querySelectorAll('model-viewer');
        list.forEach(mvEl => {
            // when model loads, build map
            mvEl.addEventListener('load', () => {
                try {
                    buildDynamicMaps(mvEl);
                } catch (e) {
                    console.error('customize_prefix_patch build error', e);
                }
            });
            // also try an immediate build if model already parsed
            if (mvEl.model) {
                try { buildDynamicMaps(mvEl); } catch (e) { /* ignore */ }
            }
        });
    }

    // run at DOM ready and also on demand
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        hookAllModelViewers();
    } else {
        window.addEventListener('DOMContentLoaded', hookAllModelViewers);
    }

    // expose manual trigger
    window.customizePrefixPatch = {
        rebuild: function (mv) { return buildDynamicMaps(mv || document.querySelector('model-viewer')); }
    };

})();
