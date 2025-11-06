// scripts/build-cavite.js
// Generates ALL Cavite city/municipality barangay JSONs
// Output dir: /RADS-TOOLING/assets/data/calabarzon/cavite
// Source: PSGC Cloud API (fast, official JSON)

import fs from "node:fs/promises";
import path from "node:path";

const API = "https://psgc.cloud/api/v1";

// EXACT output folder base you asked for (no /geo/)
const OUT_DIR = "/RADS-TOOLING/assets/data/calabarzon/cavite";

// Cavite province code (PSGC)
const CAVITE_CODE = "042100000";

// Map the exact city/muni → filename you’re using
const NAME_TO_FILE = {
  "Bacoor City": "bacoor.json",
  "Imus City": "imus.json",
  "Dasmariñas City": "dasmarinas.json",
  "General Mariano Alvarez": "gma.json",
  "General Trias City": "general_trias.json",
  "Trece Martires City": "trece_martires.json",
  "Tagaytay City": "tagaytay.json",
  "Silang": "silang.json",
  "Tanza": "tanza.json",
  "Naic": "naic.json",
  "Carmona City": "carmona.json",
  "Cavite City": "cavite_city.json"
};

const PATH_FOR = (file) => path.join(OUT_DIR, file);
const ABS_TO_URL = (file) => `/RADS-TOOLING/assets/data/calabarzon/cavite/${file}`;

async function ensureDir(p) { await fs.mkdir(p, { recursive: true }); }

async function getJSON(url) {
  const r = await fetch(url, { cache: "no-store" });
  if (!r.ok) throw new Error(`HTTP ${r.status} @ ${url}`);
  return r.json();
}

// 1) Get all cities/municipalities of Cavite
async function listCitiesMunicipalities() {
  const url = `${API}/provinces/${CAVITE_CODE}/cities-municipalities`;
  return getJSON(url); // [{name, code, type, ...}, ...]
}

// 2) Get all barangays by city/municipality code
async function listBarangays(item) {
  const key = /City/i.test(item.type) ? "city_code" : "municipality_code";
  const url = `${API}/barangays?${key}=${item.code}&per_page=10000`;
  const data = await getJSON(url); // [{name,...}]
  // Return sorted names
  return data.map(b => b.name).sort((a, b) => a.localeCompare(b));
}

async function writeJSON(file, data) {
  const pretty = JSON.stringify(data, null, 2);
  await fs.writeFile(file, pretty + "\n", "utf8");
}

async function main() {
  await ensureDir(OUT_DIR);

  console.log("Fetching Cavite LGUs…");
  const items = await listCitiesMunicipalities();

  // Build cities.json mapping (display name → absolute URL path you want)
  const citiesMap = {};
  for (const item of items) {
    const file = NAME_TO_FILE[item.name];
    if (!file) {
      console.warn(`! Skipping unknown LGU (not in NAME_TO_FILE): ${item.name}`);
      continue;
    }
    citiesMap[item.name] = ABS_TO_URL(file);
  }
  await writeJSON(PATH_FOR("cities.json"), citiesMap);
  console.log("✓ wrote cities.json");

  // Write each barangay file
  for (const item of items) {
    const file = NAME_TO_FILE[item.name];
    if (!file) continue;

    const out = PATH_FOR(file);
    console.log(`→ ${item.name} …`);
    const brgys = await listBarangays(item);
    await writeJSON(out, brgys);
    console.log(`  ✓ ${file} (${brgys.length} barangays)`);
  }

  console.log("\nALL DONE ✅  (Cavite barangay JSONs generated)");
}

main().catch(err => {
  console.error("ERROR:", err);
  process.exit(1);
});
