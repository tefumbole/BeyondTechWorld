import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

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

export function AdminTestResultsPage() {
  const [reports, setReports] = useState([]);
  const [error, setError] = useState('');

  useEffect(() => {
    fetch(`${API}/system-test/reports`, { headers: authHeaders() })
      .then(async (res) => {
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Could not load results');
        setReports(json.reports || []);
      })
      .catch((err) => setError(err.message));
  }, []);

  return (
    <div>
      <Link className="text-sm text-[#003D82] underline" to="/admin/help">Back to Help</Link>
      <h1 className="text-3xl font-bold text-[#003D82] mt-2">Finished test results</h1>
      <p className="text-gray-600 mb-4">Drafts in progress are not listed.</p>
      {error && <p className="text-red-700">{error}</p>}
      <ul className="bg-white rounded-xl border divide-y">
        {reports.map((report) => (
          <li key={report.id} className="p-4">
            <Link className="text-[#003D82] font-semibold text-lg" to={`/admin/help/results/${report.id}`}>{report.tester_name}</Link>
            <p className="text-sm text-gray-600">{report.tester_phone} · works {report.counts?.works ?? 0} · does not work {report.counts?.fails ?? 0} · not tested {report.counts?.skipped ?? 0}</p>
          </li>
        ))}
        {!reports.length && !error && <li className="p-4 text-gray-600">No finished tests yet.</li>}
      </ul>
    </div>
  );
}

export function AdminTestResultDetailPage() {
  const { id } = useParams();
  const [report, setReport] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    fetch(`${API}/system-test/reports/${id}`, { headers: authHeaders() })
      .then(async (res) => {
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Could not open this result');
        setReport(json.report);
      })
      .catch((err) => setError(err.message));
  }, [id]);

  return (
    <div>
      <Link className="text-sm text-[#003D82] underline" to="/admin/help/results">Back to results</Link>
      {error && <p className="text-red-700 mt-3">{error}</p>}
      {report && (
        <article className="mt-3 bg-white rounded-xl border p-5">
          <h1 className="text-3xl font-bold text-[#003D82]">{report.tester_name}</h1>
          <p className="text-gray-600">{report.tester_phone}</p>
          <p className="mt-2">Works {report.counts?.works}. Does not work {report.counts?.fails}. Not tested {report.counts?.skipped}.</p>
          {report.summary && <p className="mt-3"><strong>Note:</strong> {report.summary}</p>}
          <ol className="mt-4 space-y-3">
            {(report.rows || []).map((row) => (
              <li key={row.id || row.number} className="border-t pt-3">
                <p className="font-semibold">{row.number}. {row.label}</p>
                <p>{row.text}</p>
                {row.note && <p className="text-sm text-gray-600">{row.note}</p>}
              </li>
            ))}
          </ol>
        </article>
      )}
    </div>
  );
}
