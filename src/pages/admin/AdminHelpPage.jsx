import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { menuSnapshot } from '@/config/systemTestCatalog';

const API = import.meta.env.VITE_API_URL || '/api';

function authHeaders() {
  try {
    const parsed = JSON.parse(localStorage.getItem('alpha_supabase_auth') || '{}');
    const token = parsed?.access_token || parsed?.currentSession?.access_token;
    return token ? { Authorization: `Bearer ${token}` } : {};
  } catch {
    return {};
  }
}

export default function AdminHelpPage() {
  const menus = menuSnapshot();
  const [reports, setReports] = useState([]);

  useEffect(() => {
    fetch(`${API}/system-test/reports`, { headers: authHeaders() })
      .then((res) => res.json())
      .then((json) => setReports(json.reports || []))
      .catch(() => setReports([]));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-bold tracking-[0.16em] uppercase text-[#8a6d1d]">Help</p>
        <h1 className="text-3xl font-bold text-[#003D82]">How to use this system</h1>
      </div>
      <section className="bg-white rounded-xl border p-5 space-y-3 text-gray-700">
        <p>Sign in from the public site with your username and password. After the password, a WhatsApp code arrives. Enter it to open the blue admin menu.</p>
        <p>The blue menu is the office. Open the area you need, do the work, and sign out from the bottom of the menu when you finish.</p>
        <p>Do not delete a real record, pay real money, or empty the database while you are learning the screens.</p>
        <p>
          The public test is at <Link className="text-[#003D82] font-semibold underline" to="/system-test">/system-test</Link>.
          It is split into pages. A person can save and continue later, retrieve the test by confirming the WhatsApp number, and send it only after every question is answered.
        </p>
      </section>
      <section className="bg-white rounded-xl border p-5">
        <h2 className="text-xl font-bold text-[#003D82] mb-3">Current menus</h2>
        <h3 className="font-semibold mb-2">Public menu</h3>
        <ol className="list-decimal pl-5 mb-4">
          {menus.publicMenu.map((item) => (
            <li key={item.path}>{item.label} — {item.path}</li>
          ))}
        </ol>
        <h3 className="font-semibold mb-2">Admin menu</h3>
        <ol className="list-decimal pl-5">
          {menus.adminMenu.map((item) => (
            <li key={`${item.group}-${item.section}-${item.path}`}>{item.group} / {item.section === item.label ? item.label : `${item.section} / ${item.label}`} — {item.path}</li>
          ))}
        </ol>
      </section>
      <section className="bg-white rounded-xl border p-5">
        <div className="flex items-center justify-between gap-3 mb-3">
          <h2 className="text-xl font-bold text-[#003D82]">Finished test results</h2>
          <Link className="text-[#003D82] underline" to="/admin/help/results">Open the full list</Link>
        </div>
        {reports.length === 0 && <p className="text-gray-600">No finished tests yet. Drafts in progress do not appear here.</p>}
        <ul className="divide-y">
          {reports.slice(0, 8).map((report) => (
            <li key={report.id} className="py-2">
              <Link className="text-[#003D82] font-semibold" to={`/admin/help/results/${report.id}`}>{report.tester_name}</Link>
              <span className="text-sm text-gray-500"> · {report.tester_phone} · does not work {report.counts?.fails ?? 0}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
