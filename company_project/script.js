
const state = {
  page: 1,
  pageSize: 25,
  sortBy: 'full_name',
  sortDir: 'asc',
  total: 0,
  filteredTotal: 0,
  salaryBounds: { min: 0, max: 0 }
};

const el = (id) => document.getElementById(id);

async function api(url, options = {}) {
  const res = await fetch(url, options);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
}

function collectFilters() {
  return {
    name: el('nameFilter').value.trim(),
    job_title: el('jobFilter').value.trim(),
    department_id: el('departmentFilter').value,
    address: el('addressFilter').value.trim(),
    town_id: el('townFilter').value,
    salary_min: el('salaryMin').value,
    salary_max: el('salaryMax').value
  };
}

function toQuery(params) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined) q.set(k, v);
  });
  return q.toString();
}

async function loadMetadata() {
  const data = await api('api/metadata.php');
  fillSelect(el('departmentFilter'), data.departments, 'All departments');
  fillSelect(el('townFilter'), data.towns, 'All towns');
  fillSelect(el('departmentId'), data.departments, 'Select department');
  fillSelect(el('townId'), data.towns, 'Select town');

  const projects = el('projectIds');
  projects.innerHTML = '';
  data.projects.forEach(p => {
    const option = document.createElement('option');
    option.value = p.id;
    option.textContent = p.name;
    projects.appendChild(option);
  });

  state.salaryBounds = data.salary;
  ['salaryMin', 'salaryMax', 'salaryMinRange', 'salaryMaxRange'].forEach(id => {
    el(id).min = data.salary.min;
    el(id).max = data.salary.max;
  });

  el('salaryMin').value = data.salary.min;
  el('salaryMax').value = data.salary.max;
  el('salaryMinRange').value = data.salary.min;
  el('salaryMaxRange').value = data.salary.max;
  updateSalaryTexts();
}

function fillSelect(select, items, firstText) {
  select.innerHTML = `<option value="">${firstText}</option>`;
  items.forEach(item => {
    const option = document.createElement('option');
    option.value = item.id;
    option.textContent = item.name;
    select.appendChild(option);
  });
}

function updateSalaryTexts() {
  let minVal = Number(el('salaryMin').value || state.salaryBounds.min);
  let maxVal = Number(el('salaryMax').value || state.salaryBounds.max);
  if (minVal > maxVal) [minVal, maxVal] = [maxVal, minVal];
  el('salaryMinText').textContent = minVal;
  el('salaryMaxText').textContent = maxVal;
}

function syncSalaryFromRanges() {
  let minVal = Number(el('salaryMinRange').value);
  let maxVal = Number(el('salaryMaxRange').value);
  if (minVal > maxVal) [minVal, maxVal] = [maxVal, minVal];
  el('salaryMin').value = minVal;
  el('salaryMax').value = maxVal;
  el('salaryMinRange').value = minVal;
  el('salaryMaxRange').value = maxVal;
  updateSalaryTexts();
}

function syncSalaryFromNumbers() {
  let minVal = Number(el('salaryMin').value || state.salaryBounds.min);
  let maxVal = Number(el('salaryMax').value || state.salaryBounds.max);
  if (minVal > maxVal) [minVal, maxVal] = [maxVal, minVal];
  el('salaryMin').value = minVal;
  el('salaryMax').value = maxVal;
  el('salaryMinRange').value = minVal;
  el('salaryMaxRange').value = maxVal;
  updateSalaryTexts();
}

async function loadEmployees() {
  const filters = collectFilters();
  const qs = toQuery({
    ...filters,
    page: state.page,
    page_size: state.pageSize,
    sort_by: state.sortBy,
    sort_dir: state.sortDir
  });
  const data = await api(`api/employees.php?${qs}`);
  state.total = data.total;
  state.filteredTotal = data.filteredTotal;
  renderTable(data.rows);
  renderInfo(data.rows.length);
}

function renderTable(rows) {
  const body = el('tableBody');
  body.innerHTML = '';
  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="10">No records found.</td></tr>';
    return;
  }

  rows.forEach((row, index) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${(state.page - 1) * state.pageSize + index + 1}</td>
      <td>${escapeHtml(row.full_name || '')}</td>
      <td>${escapeHtml(row.job_title || '')}</td>
      <td>${escapeHtml(row.department_name || '')}</td>
      <td>${Number(row.salary).toFixed(2)}</td>
      <td>${escapeHtml(row.address_text || '')}</td>
      <td>${escapeHtml(row.town || '')}</td>
      <td>${escapeHtml(row.projects || '')}</td>
      <td><button class="small-btn" onclick="openEdit(${row.employee_id})">Edit</button></td>
      <td><button class="small-btn danger" onclick="deleteEmployee(${row.employee_id})">Delete</button></td>
    `;
    body.appendChild(tr);
  });
}

function renderInfo(currentCount) {
  const totalPages = Math.max(1, Math.ceil(state.filteredTotal / state.pageSize));
  el('pageInfo').textContent = `Page ${state.page}/${totalPages}`;
  el('countInfo').textContent = `Showing ${currentCount} | Filtered ${state.filteredTotal} | Total ${state.total}`;
  el('prevBtn').disabled = state.page <= 1;
  el('nextBtn').disabled = state.page >= totalPages;

  document.querySelectorAll('th[data-sort]').forEach(th => {
    const key = th.dataset.sort;
    const base = th.textContent.replace(/ [▲▼]$/, '');
    th.textContent = base;
    if (key === state.sortBy) th.textContent += state.sortDir === 'asc' ? ' ▲' : ' ▼';
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function resetForm() {
  el('employeeForm').reset();
  el('employeeId').value = '';
  Array.from(el('projectIds').options).forEach(o => o.selected = false);
}

function openModal(title) {
  el('modalTitle').textContent = title;
  el('modal').classList.remove('hidden');
}

function closeModal() {
  el('modal').classList.add('hidden');
}

async function openEdit(id) {
  const row = await api(`api/employees.php?id=${id}`);
  el('employeeId').value = row.employee_id;
  el('firstName').value = row.first_name || '';
  el('middleName').value = row.middle_name || '';
  el('lastName').value = row.last_name || '';
  el('jobTitle').value = row.job_title || '';
  el('departmentId').value = row.department_id || '';
  el('salary').value = row.salary || '';
  el('addressText').value = row.address_text || '';
  el('townId').value = row.town_id || '';
  Array.from(el('projectIds').options).forEach(o => {
    o.selected = row.project_ids.includes(Number(o.value));
  });
  openModal('Edit employee');
}
window.openEdit = openEdit;

async function deleteEmployee(id) {
  if (!confirm('Delete this employee?')) return;
  try {
    await api('api/employees.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ employee_id: id })
    });
    await loadEmployees();
  } catch (e) {
    alert(e.message);
  }
}
window.deleteEmployee = deleteEmployee;

async function submitForm(event) {
  event.preventDefault();
  const employeeId = el('employeeId').value;
  const payload = {
    employee_id: employeeId ? Number(employeeId) : undefined,
    first_name: el('firstName').value.trim(),
    middle_name: el('middleName').value.trim(),
    last_name: el('lastName').value.trim(),
    job_title: el('jobTitle').value.trim(),
    department_id: Number(el('departmentId').value),
    salary: Number(el('salary').value),
    address_text: el('addressText').value.trim(),
    town_id: Number(el('townId').value),
    project_ids: Array.from(el('projectIds').selectedOptions).map(o => Number(o.value))
  };

  try {
    await api('api/employees.php', {
      method: employeeId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    closeModal();
    resetForm();
    await loadEmployees();
  } catch (e) {
    alert(e.message);
  }
}

async function exportExcel() {
  const filters = collectFilters();
  const qs = toQuery({ ...filters, export_all: 1, sort_by: state.sortBy, sort_dir: state.sortDir });
  const data = await api(`api/employees.php?${qs}`);
  const rows = data.rows.map((r, index) => ({
    '#': index + 1,
    'Full name': r.full_name,
    'Job title': r.job_title,
    'Department': r.department_name,
    'Salary': r.salary,
    'Address': r.address_text,
    'Town': r.town,
    'Projects': r.projects
  }));
  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Employees');
  XLSX.writeFile(wb, 'employees.xlsx');
}

function clearFilters() {
  el('nameFilter').value = '';
  el('jobFilter').value = '';
  el('departmentFilter').value = '';
  el('addressFilter').value = '';
  el('townFilter').value = '';
  el('salaryMin').value = state.salaryBounds.min;
  el('salaryMax').value = state.salaryBounds.max;
  syncSalaryFromNumbers();
  state.page = 1;
  loadEmployees();
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadMetadata();
  await loadEmployees();

  el('searchBtn').addEventListener('click', () => { state.page = 1; loadEmployees(); });
  el('clearBtn').addEventListener('click', clearFilters);
  el('excelBtn').addEventListener('click', exportExcel);
  el('addBtn').addEventListener('click', () => { resetForm(); openModal('Add employee'); });
  el('closeModal').addEventListener('click', closeModal);
  el('cancelBtn').addEventListener('click', closeModal);
  el('employeeForm').addEventListener('submit', submitForm);
  el('prevBtn').addEventListener('click', () => { if (state.page > 1) { state.page--; loadEmployees(); } });
  el('nextBtn').addEventListener('click', () => { state.page++; loadEmployees(); });

  el('salaryMinRange').addEventListener('input', syncSalaryFromRanges);
  el('salaryMaxRange').addEventListener('input', syncSalaryFromRanges);
  el('salaryMin').addEventListener('input', syncSalaryFromNumbers);
  el('salaryMax').addEventListener('input', syncSalaryFromNumbers);

  document.querySelectorAll('th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      if (state.sortBy === key) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
      else { state.sortBy = key; state.sortDir = 'asc'; }
      state.page = 1;
      loadEmployees();
    });
  });
});
