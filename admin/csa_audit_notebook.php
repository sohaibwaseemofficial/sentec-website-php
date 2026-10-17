<?php
/**
 * CSA Audit Notebook & Bank Reconciler
 * SENTEC - NED University of Engineering & Technology
 */
include 'header.php';
require_once __DIR__ . '/../image_utils.php';

// Strict Super Super Admin access check
if (($_SESSION['admin_role'] ?? '') !== 'super_super_admin') {
    echo '<div class="container py-5 text-center">';
    echo '  <div class="glass-panel mx-auto p-5" style="max-width: 600px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(15, 23, 42, 0.85);">';
    echo '    <div class="mb-3"><i class="fas fa-user-shield text-danger" style="font-size: 3.5rem;"></i></div>';
    echo '    <h3 class="text-white fw-bold">403 - Restricted Access</h3>';
    echo '    <p class="text-muted mt-2 mb-4">The CSA Financial Audit Reconciler is strictly restricted to <strong>Super Super Admin</strong> clearance level.</p>';
    echo '    <a href="index.php" class="btn btn-outline-info px-4 py-2"><i class="fas fa-arrow-left me-2"></i>Return to Dashboard</a>';
    echo '  </div>';
    echo '</div>';
    include 'footer.php';
    exit();
}

$geminiDefaultKey = getenv('GEMINI_API_KEY') ?: base64_decode("QVEuQWI4Uk42SzlubVZjUFRBSllia1RTU3A1dEhsT1U2UUU0WUtqa2MwQjRZQzVtbm9WRnc=");
?>

<!-- Dependencies -->
<!-- LocalForage (IndexedDB Persistent Storage) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>
<!-- SheetJS (Excel Import & Export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<!-- jsPDF (4-Per-Page A4 Audit PDF Booklet) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
  .csa-container {
    padding: 24px 20px;
    color: #e2e8f0;
    font-family: 'Plus Jakarta Sans', sans-serif;
  }
  .csa-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 20px;
    transition: all 0.2s ease;
  }
  .csa-card:hover {
    border-color: rgba(0, 255, 148, 0.3);
  }
  .badge-csa {
    background: rgba(0, 255, 148, 0.15);
    color: #00FF94;
    border: 1px solid rgba(0, 255, 148, 0.3);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
  }
  .btn-csa-primary {
    background: linear-gradient(135deg, #00FF94, #00cc7a);
    color: #020617;
    font-weight: 700;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .btn-csa-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 255, 148, 0.35);
  }
  .btn-csa-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #020617;
    font-weight: 700;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
  }
  .btn-csa-indigo {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    font-weight: 700;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
  }
  .btn-csa-subtle {
    background: rgba(30, 41, 59, 0.8);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 11px;
    cursor: pointer;
  }
  .btn-csa-subtle:hover {
    background: rgba(51, 65, 85, 0.9);
    color: #ffffff;
  }
  .csa-table th {
    background: #0f172a;
    color: #94a3b8;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 2px solid #1e293b;
    position: sticky;
    top: 0;
    z-index: 10;
  }
  .csa-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #1e293b;
    font-size: 12px;
  }
  .csa-table tr:hover td {
    background: rgba(30, 41, 59, 0.4);
  }
  .csa-input {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid #334155;
    color: #f8fafc;
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 12px;
    outline: none;
  }
  .csa-input:focus {
    border-color: #00FF94;
    box-shadow: 0 0 0 2px rgba(0, 255, 148, 0.2);
  }
  .csa-input-table {
    background: transparent;
    border: 1px solid transparent;
    color: inherit;
    border-radius: 4px;
    padding: 2px 4px;
    font-size: 12px;
    width: 100%;
  }
  .csa-input-table:focus {
    background: #1e293b;
    border-color: #00FF94;
    color: #ffffff;
    outline: none;
  }
</style>

<div class="csa-container">
  
  <!-- PAGE HEADER & GEMINI API KEY BAR -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h2 class="h4 mb-0 fw-bold" style="color: #00FF94;">
          <i class="fas fa-balance-scale me-2"></i>CSA Audit Notebook & Bank Reconciler
        </h2>
        <span class="badge-csa">NED University Student Affairs</span>
      </div>
      <p class="text-muted small mb-0 mt-1">
        Chronological Bank Statement Reconciliation, Duplicate Deduplicator & Official 4-Per-Page A4 PDF Booklet Generator
      </p>
    </div>

    <!-- Gemini API Key & Model Settings -->
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="max-width: 380px;">
        <span class="input-group-text bg-dark border-secondary text-muted" title="Gemini API Key">
          <i class="fas fa-key text-warning"></i>
        </span>
        <input id="apiKeyInput" type="password" value="<?= htmlspecialchars($geminiDefaultKey) ?>" 
               placeholder="Gemini API Key" class="form-control bg-dark text-white border-secondary" />
        <select id="modelSelect" class="form-select bg-dark text-white border-secondary" style="max-width: 195px;">
          <option value="gemini-3.5-flash-lite" selected>gemini-3.5-flash-lite (High Quota)</option>
          <option value="gemini-flash-lite-latest">gemini-flash-lite-latest</option>
          <option value="gemini-3.1-flash-lite">gemini-3.1-flash-lite</option>
          <option value="gemini-3-flash-preview">gemini-3-flash-preview</option>
          <option value="gemini-3.5-flash">gemini-3.5-flash</option>
        </select>
        <button onclick="saveApiKey()" class="btn btn-sm btn-outline-success" title="Save API Key locally">
          <i class="fas fa-save"></i>
        </button>
        <button onclick="resetDefaultApiKey()" class="btn btn-sm btn-outline-warning" title="Reset to Verified Sentec Default API Key">
          <i class="fas fa-undo"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- WORKFLOW STEPS / CONTROL CARDS -->
  <div class="row g-3 mb-4">
    
    <!-- CARD 1: SENTEC DATABASE 1-CLICK SYNC -->
    <div class="col-12 col-md-4">
      <div class="csa-card h-100 d-flex flex-column justify-content-between">
        <div>
          <span class="text-uppercase fw-bold text-success small" style="font-size: 11px;">
            <i class="fas fa-database me-1"></i>Step 1 • Live Sentec Database
          </span>
          <h5 class="h6 fw-bold mt-1 text-white">Import All Event & Social Registrations</h5>
          <p class="text-muted small mb-3">
            Pulls all 250+ event and social payment screenshots directly from your Cloudinary storage and database records.
          </p>
        </div>
        <div>
          <button id="btnDbSync" onclick="importFromSentecDB()" class="btn-csa-primary w-100 py-2">
            <i class="fas fa-cloud-download-alt me-2"></i>Fetch All Registrations from DB
          </button>
          <div id="dbSyncStatus" class="text-center text-muted small mt-2">Ready to sync</div>
        </div>
      </div>
    </div>

    <!-- CARD 2: BULK UPLOAD MORE RECEIPTS (WEBP + CLOUDINARY) -->
    <div class="col-12 col-md-4">
      <div class="csa-card h-100 d-flex flex-column justify-content-between">
        <div>
          <span class="text-uppercase fw-bold text-warning small" style="font-size: 11px;">
            <i class="fas fa-upload me-1"></i>Step 2 • Add Extra Screenshots
          </span>
          <h5 class="h6 fw-bold mt-1 text-white">Upload New Payment Proofs</h5>
          <p class="text-muted small mb-3">
            Images are converted to WebP on the fly and uploaded permanently to Cloudinary (<code class="text-info">mas7qi9t</code>).
          </p>
        </div>
        <div>
          <input type="file" id="extraUploadInput" accept="image/*" multiple class="d-none" onchange="handleExtraUploads(event)" />
          <label for="extraUploadInput" class="btn-csa-amber w-100 text-center py-2 d-block cursor-pointer mb-2">
            <i class="fas fa-file-upload me-2"></i>Upload Screenshots (WebP &rarr; Cloudinary)
          </label>
          <div class="d-flex gap-2">
            <button onclick="addManualRow()" class="btn-csa-subtle flex-fill">
              <i class="fas fa-plus me-1"></i>+ Manual Row
            </button>
            <label class="btn-csa-subtle flex-fill text-center cursor-pointer mb-0">
              <i class="fas fa-table me-1"></i>Link Excel
              <input type="file" accept=".xlsx,.xls,.csv" class="d-none" onchange="handleRosterExcel(event)" />
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 3: AI QUEUE & AUDIT EXPORTS -->
    <div class="col-12 col-md-4">
      <div class="csa-card h-100 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-uppercase fw-bold text-info small" style="font-size: 11px;">
              <i class="fas fa-robot me-1"></i>Step 3 • Gemini AI & Exports
            </span>
            <span id="queueBadge" class="badge bg-secondary">0 Pending</span>
          </div>
          <h5 class="h6 fw-bold mt-1 text-white">AI Vision Extraction & Reconcile</h5>
          <p id="queueLog" class="text-muted small mb-2 text-truncate">
            Safe pacing (~13 reqs/min) to stay well within free tier limits.
          </p>
          <button id="startQueueBtn" onclick="toggleQueue()" class="btn-csa-amber w-100 py-2">
            <i class="fas fa-play me-2"></i>Start AI Extraction Queue
          </button>
        </div>

        <div class="mt-3 pt-3 border-top border-secondary">
          <div class="row g-2">
            <div class="col-6">
              <button onclick="exportCSAExcel()" class="btn btn-sm btn-success w-100 fw-bold">
                <i class="fas fa-file-excel me-1"></i>CSA Excel
              </button>
            </div>
            <div class="col-6">
              <button onclick="export4PerPagePDF()" class="btn-csa-indigo btn-sm w-100">
                <i class="fas fa-file-pdf me-1"></i>4-Per-Page PDF
              </button>
            </div>
            <div class="col-6">
              <button onclick="exportBackupJSON()" class="btn-csa-subtle w-100">
                <i class="fas fa-download me-1"></i>Save (.json)
              </button>
            </div>
            <div class="col-6">
              <label class="btn-csa-subtle w-100 text-center cursor-pointer mb-0">
                <i class="fas fa-folder-open me-1"></i>Load (.json)
                <input type="file" accept=".json" class="d-none" onchange="importBackupJSON(event)" />
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- STATS & SEARCH / FILTER TOOLBAR -->
  <div class="csa-card mb-4 py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      
      <!-- Stats Counters -->
      <div class="d-flex flex-wrap align-items-center gap-4 small">
        <div>Total Receipts: <strong id="statTotal" class="text-white fs-6">0</strong></div>
        <div>Unique Transactions: <strong id="statUnique" class="text-success fs-6">0</strong></div>
        <div>Duplicates Merged: <strong id="statDupes" class="text-warning fs-6">0</strong></div>
        <div>Total Verified: <strong id="statPKR" class="text-success fs-6">PKR 0</strong></div>
      </div>

      <!-- Search & Mode Toggle -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <input id="searchInput" type="text" oninput="renderTable()" placeholder="Search TID, Sender, Student, Bank..." 
               class="csa-input" style="min-width: 240px;" />
        <select id="viewMode" onchange="renderTable()" class="csa-input text-success fw-bold">
          <option value="dedup">CSA Audit View (Deduplicated & Chronologically Sorted)</option>
          <option value="raw">Raw Notebook View (All Uploads Individually)</option>
        </select>
        <button onclick="retryFlagged()" class="btn btn-sm btn-outline-warning" title="Retry all Flagged / Error receipts">
          <i class="fas fa-redo me-1"></i> Retry Flagged
        </button>
        <button onclick="clearAllData()" class="btn btn-sm btn-outline-danger" title="Clear Notebook">
          <i class="fas fa-trash-alt"></i> Reset
        </button>
      </div>

    </div>
  </div>

  <!-- NOTEBOOK TABLE -->
  <div class="csa-card p-0 overflow-hidden">
    <div class="table-responsive" style="max-height: 65vh;">
      <table class="table table-dark table-hover csa-table mb-0 align-middle">
        <thead>
          <tr>
            <th style="min-width: 100px;">Audit ID / PDF</th>
            <th style="min-width: 60px;">Receipt</th>
            <th style="min-width: 110px;">Date (YYYY-MM-DD)</th>
            <th style="min-width: 80px;">Time (24h)</th>
            <th style="min-width: 130px;">Ref / TID</th>
            <th style="min-width: 110px;">Bank / Wallet</th>
            <th style="min-width: 130px;">Sender Title</th>
            <th style="min-width: 130px;">Sender Account / IBAN</th>
            <th style="min-width: 90px;">PKR</th>
            <th style="min-width: 60px;" class="text-center">Passes</th>
            <th style="min-width: 240px;">Covered Students (Click to Edit)</th>
            <th style="min-width: 120px;">Status / Action</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr>
            <td colspan="12" class="text-center py-5 text-muted">
              <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
              No records in notebook. Click <strong>"Fetch All Registrations from DB"</strong> to load existing payments, or upload screenshots.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- IMAGE PREVIEW MODAL -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-secondary text-white">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title text-success fw-bold" id="modalTitle">Payment Screenshot</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="modalImg" src="" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;" />
      </div>
      <div class="modal-footer border-secondary py-1 justify-content-between">
        <a id="modalOpenExternal" href="#" target="_blank" class="btn btn-sm btn-outline-info">
          <i class="fas fa-external-link-alt me-1"></i>Open Full Image
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// =====================================================
// 1. STATE & INDEXEDDB PERSISTENCE (LocalForage)
// =====================================================
localforage.config({ name: "SENTEC_NED_CSA_Audit", storeName: "csa_receipts" });

let records = [];
let rosterMap = {};
let isQueueRunning = false;
const defaultVerifiedKey = <?= json_encode($geminiDefaultKey) ?>;

window.addEventListener("DOMContentLoaded", async () => {
  const savedKey = localStorage.getItem("SENTEC_CSA_GEMINI_KEY");
  // If the browser cached the typo key with TSPp or empty/short key, automatically replace with verified key
  if (!savedKey || savedKey.includes("TSPp5t") || savedKey.length < 25) {
    localStorage.setItem("SENTEC_CSA_GEMINI_KEY", defaultVerifiedKey);
    document.getElementById("apiKeyInput").value = defaultVerifiedKey;
  } else {
    document.getElementById("apiKeyInput").value = savedKey;
  }

  const savedRecords = await localforage.getItem("CSA_RECORDS");
  const savedRoster = await localforage.getItem("CSA_ROSTER");
  if (savedRecords && savedRecords.length) {
    records = savedRecords;
  }
  if (savedRoster) {
    rosterMap = savedRoster;
  }
  renderTable();
});

function saveApiKey() {
  const key = document.getElementById("apiKeyInput").value.trim();
  localStorage.setItem("SENTEC_CSA_GEMINI_KEY", key);
  alert("Gemini API Key saved locally!");
}

function resetDefaultApiKey() {
  localStorage.setItem("SENTEC_CSA_GEMINI_KEY", defaultVerifiedKey);
  document.getElementById("apiKeyInput").value = defaultVerifiedKey;
  alert("Reset to verified Google Gemini API Key!");
}

async function persistRecords() {
  await localforage.setItem("CSA_RECORDS", records);
  renderTable();
}

// =====================================================
// 2. 1-CLICK DATABASE IMPORT (EVENT + SOCIAL)
// =====================================================
async function importFromSentecDB() {
  const btn = document.getElementById("btnDbSync");
  const statusEl = document.getElementById("dbSyncStatus");
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Fetching Registrations...';

  try {
    // Add cache buster timestamp query parameter & no-store
    const res = await fetch("api_csa_sync.php?action=fetch_all_registrations&_t=" + Date.now(), {
      cache: "no-store"
    });
    const json = await res.json();

    if (!json.success) {
      throw new Error(json.error || "Failed to fetch registrations");
    }

    let addedCount = 0;
    let updatedCount = 0;
    let existingCount = 0;

    for (const r of json.records) {
      // Find existing record strictly by its unique registration id (e.g. EVENT_101 or SOC_217)
      const existingIdx = records.findIndex(item => item.id === r.id);

      if (existingIdx === -1) {
        // Record is not in notebook (newly registered or was deleted)
        records.push({
          id: r.id,
          sourceTable: r.sourceTable,
          sourceId: r.sourceId,
          category: r.category,
          student: r.student,
          phone: r.phone || "",
          email: r.email || "",
          fileName: r.fileName,
          imageUrl: r.imageUrl,
          dataUrl: null,
          date: "",
          time: "",
          tid: "",
          bank: "",
          senderTitle: "",
          senderAccount: "",
          amount: r.amount || 0,
          status: "Pending"
        });
        addedCount++;
      } else {
        const existing = records[existingIdx];
        // If payment screenshot was changed/updated in admin panel:
        if (existing.imageUrl !== r.imageUrl) {
          existing.imageUrl = r.imageUrl;
          existing.fileName = r.fileName;
          existing.dataUrl = null; // Clear old cached base64 image so new receipt loads!
          existing.date = "";
          existing.time = "";
          existing.tid = "";
          existing.bank = "";
          existing.senderTitle = "";
          existing.senderAccount = "";
          existing.status = "Pending"; // Mark as Pending so AI extracts the new screenshot
          existing.retries = 0;
          existing.student = r.student;
          if (r.amount) existing.amount = r.amount;
          if (r.phone) existing.phone = r.phone;
          if (r.email) existing.email = r.email;
          updatedCount++;
        } else {
          // Keep verified status, but sync updated student info if any
          existing.student = r.student;
          existingCount++;
        }
      }
    }

    await persistRecords();
    statusEl.innerText = `Synced: ${addedCount} new, ${updatedCount} updated, ${existingCount} up-to-date`;
    alert(`Database Sync Complete!\n• ${addedCount} new records added\n• ${updatedCount} records updated with new payment screenshots\n• ${existingCount} already up-to-date\n\nClick 'Start AI Extraction Queue' to extract any newly synced screenshots.`);
  } catch (err) {
    alert("Error syncing database: " + err.message);
    statusEl.innerText = "Error: " + err.message;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-cloud-download-alt me-2"></i>Fetch All Registrations from DB';
  }
}

// =====================================================
// 3. EXTRA SCREENSHOT UPLOADER (WEBP & CLOUDINARY)
// =====================================================
async function handleExtraUploads(e) {
  const files = Array.from(e.target.files);
  if (!files.length) return;

  const logEl = document.getElementById("queueLog");
  let added = 0;

  for (let i = 0; i < files.length; i++) {
    const f = files[i];
    logEl.innerText = `Converting to WebP & uploading to Cloudinary (${i + 1}/${files.length}): ${f.name}...`;

    const formData = new FormData();
    formData.append("action", "upload_receipt");
    formData.append("file", f);

    try {
      const res = await fetch("api_csa_sync.php", {
        method: "POST",
        body: formData
      });
      const data = await res.json();

      if (data.success) {
        records.push({
          id: "UPLOAD_" + Date.now() + "_" + Math.random().toString(36).slice(2, 6),
          sourceTable: "manual_upload",
          sourceId: null,
          category: "Uploaded Screenshot",
          student: f.name.replace(/\.[^/.]+$/, ""),
          phone: "",
          email: "",
          fileName: data.fileName || f.name,
          imageUrl: data.url,
          dataUrl: null,
          date: "",
          time: "",
          tid: "",
          bank: "",
          senderTitle: "",
          senderAccount: "",
          amount: 0,
          status: "Pending"
        });
        added++;
      } else {
        console.error("Upload error for file:", f.name, data.error);
      }
    } catch (err) {
      console.error("Upload failed:", err);
    }
  }

  e.target.value = "";
  await persistRecords();
  logEl.innerText = `Uploaded and added ${added} screenshots to Notebook.`;
  alert(`Added ${added} screenshots to your Notebook!`);
}

function handleRosterExcel(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = async (evt) => {
    const data = new Uint8Array(evt.target.result);
    const workbook = XLSX.read(data, { type: "array" });
    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json(firstSheet, { defval: "" });

    let count = 0;
    rows.forEach((r) => {
      const keys = Object.keys(r);
      const nameKey = keys.find(k => /name/i.test(k)) || keys[0];
      const rollKey = keys.find(k => /roll|seat|id|cnic/i.test(k));
      const imgKey = keys.find(k => /screen|image|receipt|proof|file/i.test(k));

      if (imgKey && r[imgKey]) {
        const rawPath = String(r[imgKey]).trim();
        const baseName = rawPath.split(/[/\\]/).pop().toLowerCase();
        const studentStr = rollKey && r[rollKey] ? `${r[nameKey]} (${r[rollKey]})` : `${r[nameKey]}`;
        if (baseName) {
          rosterMap[baseName] = studentStr;
          count++;
        }
      }
    });

    await localforage.setItem("CSA_ROSTER", rosterMap);
    records.forEach(rec => {
      const match = rosterMap[(rec.fileName || '').toLowerCase()];
      if (match) rec.student = match;
    });
    await persistRecords();
    alert(`Linked ${count} rows from spreadsheet!`);
  };
  reader.readAsArrayBuffer(file);
}

async function addManualRow() {
  records.push({
    id: "MANUAL_" + Date.now(),
    sourceTable: "manual_entry",
    sourceId: null,
    category: "Manual Receipt",
    student: "Student Name (Roll No)",
    phone: "",
    email: "",
    fileName: "Manual_Entry.webp",
    imageUrl: "",
    dataUrl: null,
    date: new Date().toISOString().slice(0, 10),
    time: "12:00",
    tid: "TID" + Math.floor(100000 + Math.random() * 900000),
    bank: "Meezan Bank",
    senderTitle: "Account Title",
    senderAccount: "",
    amount: 500,
    status: "Verified"
  });
  await persistRecords();
}

// =====================================================
// 4. IMAGE DATAURL LOADER (WITH CLOUDINARY CROSS-ORIGIN)
// =====================================================
async function ensureDataUrl(rec) {
  if (rec.dataUrl) return rec.dataUrl;
  if (!rec.imageUrl) return null;

  try {
    const res = await fetch(rec.imageUrl, { mode: 'cors' });
    const blob = await res.blob();
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onloadend = () => {
        rec.dataUrl = reader.result;
        resolve(reader.result);
      };
      reader.readAsDataURL(blob);
    });
  } catch (err) {
    console.warn("Could not convert image to DataURL:", rec.imageUrl, err);
    return null;
  }
}

// =====================================================
// 5. GEMINI VISION EXTRACTION QUEUE (WITH AUTO-FALLBACK & PROGRESS)
// =====================================================
async function toggleQueue() {
  if (isQueueRunning) {
    isQueueRunning = false;
    document.getElementById("startQueueBtn").innerHTML = '<i class="fas fa-play me-2"></i>Resume AI Extraction Queue';
    return;
  }

  const apiKey = document.getElementById("apiKeyInput").value.trim();
  if (!apiKey) {
    alert("Please enter a valid Gemini API Key.");
    return;
  }

  isQueueRunning = true;
  document.getElementById("startQueueBtn").innerHTML = '<i class="fas fa-pause me-2"></i>Pause Queue';
  const logEl = document.getElementById("queueLog");

  while (isQueueRunning) {
    // Only pick records that are Pending or have retried < 2 times
    const nextRec = records.find(r => (r.status === "Pending" || (r.status === "Error" && (r.retries || 0) < 2)) && r.imageUrl);
    if (!nextRec) {
      isQueueRunning = false;
      document.getElementById("startQueueBtn").innerHTML = '<i class="fas fa-play me-2"></i>Start AI Extraction Queue';
      logEl.innerText = "All screenshots have been processed!";
      break;
    }

    const totalCount = records.length;
    const completedCount = records.filter(r => r.status === "Verified" || r.status === "Flagged" || r.status.startsWith("Flagged")).length;
    const itemNum = completedCount + 1;

    // Check if another identical receipt URL has already been verified (Instant duplicate match!)
    const twin = records.find(r => r.id !== nextRec.id && r.imageUrl === nextRec.imageUrl && r.status === "Verified");
    if (twin && twin.date && twin.tid) {
      nextRec.date = twin.date;
      nextRec.time = twin.time;
      nextRec.tid = twin.tid;
      nextRec.bank = twin.bank;
      nextRec.senderTitle = twin.senderTitle;
      nextRec.senderAccount = twin.senderAccount;
      if (twin.amount) nextRec.amount = twin.amount;
      nextRec.status = "Verified";
      logEl.innerText = `⚡ [${itemNum}/${totalCount}] Instant twin match for duplicate (${nextRec.fileName}) - 0 API calls used`;
      await persistRecords();
      continue;
    }

    logEl.innerText = `[${itemNum}/${totalCount}] Loading image: ${nextRec.fileName}...`;
    const dataUrl = await ensureDataUrl(nextRec);

    if (!dataUrl) {
      nextRec.status = "Flagged (Image Unreadable)";
      await persistRecords();
      continue;
    }

    logEl.innerText = `[${itemNum}/${totalCount}] Extracting via Gemini Vision: ${nextRec.fileName}...`;
    const result = await callGeminiVision(apiKey, dataUrl);

    if (result.rateLimited) {
      nextRec.rateLimitRetries = (nextRec.rateLimitRetries || 0) + 1;
      if (nextRec.rateLimitRetries >= 2) {
        nextRec.status = "Flagged (Rate Limit)";
        logEl.innerText = `⏳ Daily rate limit hit for ${nextRec.fileName}. Skipped to keep moving.`;
        await persistRecords();
      } else {
        logEl.innerText = `⏳ Rate limit reached. Cooling down for 15s before retrying...`;
        await new Promise(r => setTimeout(r, 15000));
      }
      continue;
    }

    if (result.success) {
      const d = result.data;
      nextRec.date = d.transaction_date || "";
      nextRec.time = d.transaction_time || "00:00";
      nextRec.tid = String(d.transaction_id || "").replace(/\s+/g, "");
      nextRec.bank = d.bank_name || "";
      nextRec.senderTitle = d.sender_title || "";
      nextRec.senderAccount = d.sender_account || "";
      if (d.amount) nextRec.amount = Number(d.amount);
      nextRec.status = (d.is_valid_receipt && nextRec.date && nextRec.tid) ? "Verified" : "Flagged";
      logEl.innerText = `✅ [${itemNum}/${totalCount}] Verified ${nextRec.fileName} (TID: ${nextRec.tid}, PKR ${nextRec.amount})`;
      await persistRecords();

      // Safe ~4s pacing for stable Free Tier operation
      await new Promise(r => setTimeout(r, 4000));
    } else {
      nextRec.retries = (nextRec.retries || 0) + 1;
      if (nextRec.retries >= 2) {
        nextRec.status = "Flagged (Review Needed)";
      } else {
        nextRec.status = "Error";
      }
      logEl.innerText = `⚠️ [${itemNum}/${totalCount}] ${nextRec.fileName}: ${result.error}. Continuing...`;
      await persistRecords();
      await new Promise(r => setTimeout(r, 3000));
    }
  }
}

async function callGeminiVision(apiKey, dataUrl) {
  let model = document.getElementById("modelSelect").value;
  const base64Data = dataUrl.split(",")[1];
  const mimeMatch = dataUrl.match(/^data:([^;]+);base64,/);
  const mimeType = mimeMatch ? mimeMatch[1] : 'image/webp';

  const prompt = `Extract bank transaction details from this Pakistani payment receipt screenshot (Meezan, SadaPay, NayaPay, EasyPaisa, JazzCash, HBL, UBL, Raast, etc.).
Return strictly JSON with keys:
- is_valid_receipt (boolean)
- transaction_date (YYYY-MM-DD format strictly)
- transaction_time (24-hour HH:MM format strictly)
- transaction_id (Reference / TID / STAN / RRN / Raast ID without spaces)
- bank_name (Bank or Wallet name)
- sender_title (Sender Account Title / From Name)
- sender_account (Sender Account No or masked digits)
- amount (numeric amount in PKR)`;

  const callModel = async (modelName) => {
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${apiKey}`;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 25000); // 25s timeout

    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      signal: controller.signal,
      body: JSON.stringify({
        contents: [{
          parts: [
            { text: prompt },
            { inline_data: { mime_type: mimeType, data: base64Data } }
          ]
        }],
        generationConfig: {
          response_mime_type: "application/json",
          temperature: 0.0
        }
      })
    });
    clearTimeout(timer);
    return res;
  };

  const fallbackList = [
    model,
    "gemini-3.5-flash-lite",
    "gemini-flash-lite-latest",
    "gemini-3.1-flash-lite"
  ];
  // Deduplicate list preserving order
  const modelsToTry = [...new Set(fallbackList)];
  let lastError = null;

  for (let i = 0; i < modelsToTry.length; i++) {
    const curModel = modelsToTry[i];
    try {
      const res = await callModel(curModel);

      if (res.status === 429) {
        console.warn(`HTTP 429 on ${curModel}, trying fallback...`);
        lastError = { rateLimited: true };
        continue;
      }

      if (!res.ok) {
        console.warn(`HTTP ${res.status} on ${curModel}, trying fallback...`);
        lastError = { success: false, error: `HTTP ${res.status}` };
        continue;
      }

      const json = await res.json();
      const rawText = json.candidates?.[0]?.content?.parts?.[0]?.text || "{}";
      
      // Clean markdown code blocks (```json ... ```) or conversational intro
      let cleaned = rawText.trim();
      if (cleaned.startsWith("```")) {
        cleaned = cleaned.replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/, "");
      }
      const firstBrace = cleaned.indexOf("{");
      const lastBrace = cleaned.lastIndexOf("}");
      if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
        cleaned = cleaned.substring(firstBrace, lastBrace + 1);
      }

      const parsedData = JSON.parse(cleaned);
      return { success: true, data: parsedData };
    } catch (err) {
      console.warn(`Error on ${curModel}:`, err);
      lastError = { success: false, error: err.name === 'AbortError' ? 'Request timed out' : err.message };
    }
  }

  return lastError || { success: false, error: 'Unknown extraction error' };
}

// =====================================================
// 6. DEDUPLICATION & CHRONOLOGICAL SORT ENGINE
// =====================================================
function getDeduplicatedRows() {
  const groups = new Map();

  records.forEach((rec) => {
    const cleanTid = (rec.tid || "").trim().toUpperCase();
    const cleanUrl = (rec.imageUrl || rec.fileName || "").trim().toLowerCase();
    
    // Group by TID if available (>= 4 chars), otherwise by exact image URL/filename
    const key = cleanTid.length >= 4 ? `TID_${cleanTid}` : `IMG_${cleanUrl}`;

    if (!groups.has(key)) {
      groups.set(key, {
        groupKey: key,
        primaryRec: rec,
        memberIds: [rec.id],
        students: [rec.student]
      });
    } else {
      const g = groups.get(key);
      g.memberIds.push(rec.id);
      if (!g.students.includes(rec.student)) g.students.push(rec.student);
    }
  });

  const unified = Array.from(groups.values()).map(g => {
    const r = g.primaryRec;
    return {
      ...r,
      memberIds: g.memberIds,
      passesCount: g.memberIds.length,
      coveredStudents: g.students.join(" | "),
      sortTimestamp: `${r.date || "9999-99-99"} ${r.time || "99:99"}`
    };
  });

  // Sort chronologically by Transaction Date & Time
  unified.sort((a, b) => a.sortTimestamp.localeCompare(b.sortTimestamp));

  // Assign CSA Audit IDs and PDF coordinates
  unified.forEach((row, idx) => {
    row.auditId = `CSA-${String(idx + 1).padStart(3, "0")}`;
    const pageNo = Math.floor(idx / 4) + 1;
    const slotNo = (idx % 4) + 1;
    row.pdfLocation = `Page ${pageNo} • Box ${slotNo}`;
  });

  return unified;
}

// =====================================================
// 7. RENDER NOTEBOOK TABLE
// =====================================================
function renderTable() {
  const viewMode = document.getElementById("viewMode").value;
  const query = document.getElementById("searchInput").value.trim().toLowerCase();
  const deduped = getDeduplicatedRows();

  // Update counters
  const pendingCount = records.filter(r => r.status === "Pending").length;
  document.getElementById("queueBadge").innerText = `${pendingCount} Pending`;
  document.getElementById("statTotal").innerText = records.length;
  document.getElementById("statUnique").innerText = deduped.length;
  document.getElementById("statDupes").innerText = Math.max(0, records.length - deduped.length);

  const totalPKR = deduped.reduce((sum, r) => sum + (Number(r.amount) || 0), 0);
  document.getElementById("statPKR").innerText = `PKR ${totalPKR.toLocaleString()}`;

  const rowsToRender = viewMode === "dedup" ? deduped : records.map((r, i) => ({
    ...r,
    auditId: `RAW-${i + 1}`,
    pdfLocation: r.fileName,
    passesCount: 1,
    coveredStudents: r.student,
    memberIds: [r.id]
  }));

  const filtered = rowsToRender.filter(r => {
    if (!query) return true;
    const hay = `${r.auditId} ${r.date} ${r.time} ${r.tid} ${r.bank} ${r.senderTitle} ${r.senderAccount} ${r.coveredStudents} ${r.status}`.toLowerCase();
    return hay.includes(query);
  });

  const tbody = document.getElementById("tableBody");

  if (!filtered.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="12" class="text-center py-5 text-muted">
          <i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>
          No records match the current filter.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = filtered.map(r => {
    const statusColor = r.status === "Verified" ? "text-success" :
                        r.status === "Pending" ? "text-warning" : "text-danger";
    return `
      <tr>
        <td class="font-monospace">
          <div class="fw-bold text-success">${r.auditId}</div>
          <div class="text-muted" style="font-size: 10px;">${r.pdfLocation}</div>
        </td>
        <td>
          ${r.imageUrl ? `
            <img src="${r.imageUrl}" onclick="openImageModal('${r.imageUrl}', '${escapeHtml(r.student)}')" 
                 class="rounded border border-secondary cursor-pointer" 
                 style="width: 38px; height: 46px; object-fit: cover;" title="Click to enlarge" />
          ` : `<span class="text-muted" style="font-size: 10px;">No Img</span>`}
        </td>
        <td><input value="${escapeHtml(r.date || '')}" onchange="updateRowField('${r.id}', 'date', this.value, '${r.memberIds.join(',')}')" class="csa-input-table font-monospace" placeholder="YYYY-MM-DD" /></td>
        <td><input value="${escapeHtml(r.time || '')}" onchange="updateRowField('${r.id}', 'time', this.value, '${r.memberIds.join(',')}')" class="csa-input-table font-monospace" placeholder="HH:MM" /></td>
        <td><input value="${escapeHtml(r.tid || '')}" onchange="updateRowField('${r.id}', 'tid', this.value, '${r.memberIds.join(',')}')" class="csa-input-table font-monospace text-warning fw-bold" placeholder="TID" /></td>
        <td><input value="${escapeHtml(r.bank || '')}" onchange="updateRowField('${r.id}', 'bank', this.value, '${r.memberIds.join(',')}')" class="csa-input-table" placeholder="Bank" /></td>
        <td><input value="${escapeHtml(r.senderTitle || '')}" onchange="updateRowField('${r.id}', 'senderTitle', this.value, '${r.memberIds.join(',')}')" class="csa-input-table" placeholder="Sender Title" /></td>
        <td><input value="${escapeHtml(r.senderAccount || '')}" onchange="updateRowField('${r.id}', 'senderAccount', this.value, '${r.memberIds.join(',')}')" class="csa-input-table font-monospace" placeholder="Account" /></td>
        <td><input type="number" value="${r.amount || 0}" onchange="updateRowField('${r.id}', 'amount', Number(this.value), '${r.memberIds.join(',')}')" class="csa-input-table fw-bold text-success" /></td>
        <td class="text-center">
          <span class="badge ${r.passesCount > 1 ? 'bg-warning text-dark' : 'bg-dark border border-secondary text-light'}">${r.passesCount}</span>
        </td>
        <td>
          <input value="${escapeHtml(r.coveredStudents || '')}" onchange="updateRowField('${r.id}', 'student', this.value, '${r.id}')" class="csa-input-table" />
        </td>
        <td>
          <div class="d-flex align-items-center justify-content-between gap-1">
            <span class="fw-bold small ${statusColor}">${r.status}</span>
            <button onclick="deleteRow('${r.memberIds.join(',')}')" class="btn btn-sm btn-link text-muted p-0" title="Delete">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join("");
}

function escapeHtml(str) {
  return String(str || "").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

async function updateRowField(primaryId, field, value, memberIdsCSV) {
  const ids = memberIdsCSV.split(",");
  records.forEach(r => {
    if (ids.includes(r.id)) {
      if (field === "student" && r.id !== primaryId) return;
      r[field] = value;
      if (r.date && r.tid) r.status = "Verified";
    }
  });
  await persistRecords();
}

async function deleteRow(memberIdsCSV) {
  if (!confirm("Remove this entry from the Notebook?")) return;
  const ids = memberIdsCSV.split(",");
  records = records.filter(r => !ids.includes(r.id));
  await persistRecords();
}

function openImageModal(url, title) {
  document.getElementById("modalTitle").innerText = title;
  document.getElementById("modalImg").src = url;
  document.getElementById("modalOpenExternal").href = url;
  const modal = new bootstrap.Modal(document.getElementById("imageModal"));
  modal.show();
}

// =====================================================
// 8. EXPORTS: CSA EXCEL, 4-PER-PAGE PDF, & JSON BACKUP
// =====================================================
function exportCSAExcel() {
  const deduped = getDeduplicatedRows();
  if (!deduped.length) return alert("No records to export!");

  const csaSheetData = deduped.map(r => ({
    "Audit ID": r.auditId,
    "Transaction Date": r.date,
    "Transaction Time": r.time,
    "Reference / Transaction ID": r.tid,
    "Bank / Wallet": r.bank,
    "Sender Account Title": r.senderTitle,
    "Sender Account No / IBAN": r.senderAccount,
    "Amount Paid (PKR)": Number(r.amount) || 0,
    "Passes Covered": r.passesCount,
    "Student Name(s) & Details": r.coveredStudents,
    "PDF Booklet Location": r.pdfLocation,
    "Receipt URL": r.imageUrl,
    "Verification Status": r.status
  }));

  const rawSheetData = records.map((r, idx) => ({
    "Row #": idx + 1,
    "Category": r.category,
    "Student / Team": r.student,
    "Transaction Date": r.date,
    "Transaction Time": r.time,
    "Reference / Transaction ID": r.tid,
    "Bank / Wallet": r.bank,
    "Sender Title": r.senderTitle,
    "Sender Account": r.senderAccount,
    "Amount (PKR)": Number(r.amount) || 0,
    "Status": r.status,
    "Receipt URL": r.imageUrl
  }));

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(csaSheetData), "Chronological_CSA_Audit");
  XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(rawSheetData), "All_Raw_Uploads");
  XLSX.writeFile(wb, `SENTEC_CSA_Audit_Reconciliation_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

function prepareImageForPDF(url, maxDim = 600) {
  return new Promise((resolve) => {
    if (!url) return resolve(null);
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => {
      try {
        let w = img.width;
        let h = img.height;
        if (w > maxDim || h > maxDim) {
          if (w > h) {
            h = Math.round((h * maxDim) / w);
            w = maxDim;
          } else {
            w = Math.round((w * maxDim) / h);
            h = maxDim;
          }
        }
        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        resolve({
          dataUrl: canvas.toDataURL("image/jpeg", 0.72),
          width: w,
          height: h
        });
      } catch (err) {
        console.warn("Canvas export error:", err);
        resolve(null);
      }
    };
    img.onerror = () => resolve(null);
    img.src = url;
  });
}

async function export4PerPagePDF() {
  const deduped = getDeduplicatedRows();
  if (!deduped.length) return alert("No records to export!");

  const logEl = document.getElementById("queueLog");
  const total = deduped.length;
  const totalPages = Math.ceil(total / 4);
  logEl.innerText = `Starting PDF export for ${total} receipts (${totalPages} pages)...`;

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4", compress: true });
  const pageW = 210, pageH = 297, margin = 8;
  const cellW = (pageW - margin * 3) / 2;   // 93 mm
  const cellH = (pageH - margin * 3) / 2;   // 136.5 mm
  const headerH = 22;                       // 22 mm metadata header banner

  const slots = [
    { x: margin, y: margin },
    { x: margin * 2 + cellW, y: margin },
    { x: margin, y: margin * 2 + cellH },
    { x: margin * 2 + cellW, y: margin * 2 + cellH }
  ];

  for (let i = 0; i < total; i++) {
    const r = deduped[i];
    const slotIdx = i % 4;
    const pageNo = Math.floor(i / 4) + 1;
    if (i > 0 && slotIdx === 0) doc.addPage();

    logEl.innerText = `Rendering PDF Booklet: Box ${i + 1} of ${total} (Page ${pageNo}/${totalPages})...`;

    const { x, y } = slots[slotIdx];

    // Card border
    doc.setDrawColor(180, 180, 180);
    doc.rect(x, y, cellW, cellH);

    // Header background
    doc.setFillColor(240, 244, 248);
    doc.rect(x, y, cellW, headerH, "FD");

    doc.setTextColor(15, 23, 42);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8.5);
    doc.text(`${r.auditId}  |  ${r.date || "No Date"} ${r.time || ""}  |  PKR ${r.amount || 0}`, x + 2, y + 4.5);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(7.5);
    doc.text(`TID: ${r.tid || "N/A"}  |  ${r.bank || "Unknown Bank"}`.slice(0, 58), x + 2, y + 9.2);
    doc.text(`Sender: ${r.senderTitle || "N/A"} (${r.senderAccount || "N/A"})`.slice(0, 58), x + 2, y + 13.8);
    doc.text(`Covers (${r.passesCount}): ${r.coveredStudents}`.slice(0, 60), x + 2, y + 18.5);

    // Prepare and draw image using crisp lightweight JPEG
    const imgObj = await prepareImageForPDF(r.dataUrl || r.imageUrl);
    if (imgObj && imgObj.dataUrl) {
      try {
        const availW = cellW - 4;
        const availH = cellH - headerH - 4;
        const scale = Math.min(availW / imgObj.width, availH / imgObj.height);
        const drawW = imgObj.width * scale;
        const drawH = imgObj.height * scale;
        const drawX = x + 2 + (availW - drawW) / 2;
        const drawY = y + headerH + 2 + (availH - drawH) / 2;
        doc.addImage(imgObj.dataUrl, "JPEG", drawX, drawY, drawW, drawH, undefined, "FAST");
      } catch (e) {
        console.warn("Could not draw image for", r.auditId, e);
      }
    }

    // Yield control to browser UI thread every 2 items so page never freezes
    if (i % 2 === 0) {
      await new Promise(res => setTimeout(res, 10));
    }
  }

  logEl.innerText = "Compressing and saving PDF file...";
  await new Promise(res => setTimeout(res, 30));
  doc.save(`SENTEC_CSA_4PerPage_Audit_Booklet_${new Date().toISOString().slice(0, 10)}.pdf`);
  logEl.innerText = `✅ PDF Booklet downloaded (${totalPages} pages, ${total} receipts)!`;
}

function exportBackupJSON() {
  const blob = new Blob([JSON.stringify({ records, rosterMap })], { type: "application/json" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `SENTEC_CSA_Notebook_Backup_${new Date().toISOString().slice(0, 10)}.json`;
  a.click();
}

function importBackupJSON(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = async (evt) => {
    try {
      const parsed = JSON.parse(evt.target.result);
      if (parsed.records) records = parsed.records;
      if (parsed.rosterMap) rosterMap = parsed.rosterMap;
      await localforage.setItem("CSA_ROSTER", rosterMap);
      await persistRecords();
      alert(`Restored ${records.length} records into your Notebook!`);
    } catch (err) {
      alert("Invalid backup file: " + err.message);
    }
  };
  reader.readAsText(file);
}

async function clearAllData() {
  if (!confirm("Are you sure you want to clear all records in this notebook?")) return;
  records = [];
  rosterMap = {};
  await localforage.clear();
  renderTable();
}

async function retryFlagged() {
  let count = 0;
  records.forEach(r => {
    if (r.status.startsWith("Flagged") || r.status === "Error") {
      r.status = "Pending";
      r.retries = 0;
      count++;
    }
  });
  if (count === 0) {
    alert("No flagged or error receipts found!");
    return;
  }
  await persistRecords();
  alert(`Reset ${count} receipts back to Pending. Click 'Start AI Extraction Queue' to process them!`);
}
</script>

<?php include 'footer.php'; ?>
