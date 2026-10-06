import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import BrandLogo from '@/components/BrandLogo';
import Footer from '@/components/Footer';
import { COMPANY_NAME } from '@/constants/branding';

const API = import.meta.env.VITE_API_URL || '/api';
const STORE = 'beyond-system-test';

function readStore() {
  try {
    return JSON.parse(localStorage.getItem(STORE) || '{}');
  } catch {
    return {};
  }
}

function writeStore(patch) {
  const next = { ...readStore(), ...patch };
  localStorage.setItem(STORE, JSON.stringify(next));
  return next;
}

async function api(path, { method = 'GET', body, token, formToken } = {}) {
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['x-test-token'] = token;
  if (formToken) headers['x-form-token'] = formToken;
  const res = await fetch(`${API}/system-test${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    const error = new Error(json.error || 'The test could not be saved.');
    error.payload = json;
    error.status = res.status;
    throw error;
  }
  return json;
}

function TestChrome({ children }) {
  return (
    <div className="min-h-screen flex flex-col bg-[#f6f3ec] text-[#1c2430]">
      <header className="sticky top-0 z-40 bg-[#003D82] h-16 md:h-20 flex items-center px-4 shadow">
        <Link to="/" aria-label={COMPANY_NAME}>
          <BrandLogo variant="onDark" className="h-10 md:h-12 w-auto" alt={COMPANY_NAME} />
        </Link>
      </header>
      <main className="flex-1 w-full max-w-5xl mx-auto px-4 py-6">{children}</main>
      <Footer />
    </div>
  );
}

function ProgressBar({ progress, page, pageCount }) {
  const percent = progress?.percent || 0;
  return (
    <div className="sticky top-16 md:top-20 z-30 mb-4 rounded-2xl border border-[#e7e1d4] bg-white/95 backdrop-blur px-4 py-3 shadow">
      <div className="flex items-end justify-between gap-3">
        <div>
          <p className="text-3xl font-extrabold text-[#003D82] leading-none">{percent}%</p>
          <p className="text-sm text-gray-600 mt-1">{progress?.answered || 0} of {progress?.total || 0} answered</p>
        </div>
        <p className="text-sm font-semibold text-[#003D82]">Page {page} of {pageCount}</p>
      </div>
      <div className="mt-3 h-3 rounded-full bg-[#f3efe6] overflow-hidden border border-[#e7e1d4]">
        <div className="h-full bg-gradient-to-r from-[#003D82] to-[#D4AF37]" style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

function CountryPicker({ countries, value, onChange }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const selected = countries.find((row) => row.dial === value) || countries[0];
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return countries;
    return countries.filter((row) => row.name.toLowerCase().includes(q) || row.dial.includes(q.replace('+', '')));
  }, [countries, query]);

  return (
    <div className="relative">
      <button
        type="button"
        className="w-full flex items-center justify-between gap-3 rounded-xl border border-[#e7e1d4] bg-white px-3 py-3 text-left"
        onClick={() => setOpen((open) => !open)}
      >
        <span className="font-medium">{selected?.name || 'Choose a country'}</span>
        <span className="rounded-full bg-[#003D82] text-white text-xs font-bold px-2 py-1">+{selected?.dial}</span>
      </button>
      {open && (
        <div className="absolute z-20 mt-2 w-full rounded-xl border border-[#e7e1d4] bg-white shadow-lg">
          <input
            autoFocus
            className="w-full border-b border-[#e7e1d4] px-3 py-2 outline-none"
            placeholder="Search by country or code"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <ul className="max-h-64 overflow-y-auto">
            {filtered.map((row) => (
              <li key={`${row.name}-${row.dial}`}>
                <button
                  type="button"
                  className="w-full flex items-center justify-between px-3 py-2 hover:bg-[#f6f3ec] text-left"
                  onClick={() => {
                    onChange(row.dial);
                    setOpen(false);
                    setQuery('');
                  }}
                >
                  <span>{row.name}</span>
                  <span className="rounded-full bg-[#003D82] text-white text-xs font-bold px-2 py-1">+{row.dial}</span>
                </button>
              </li>
            ))}
            {!filtered.length && <li className="px-3 py-3 text-sm text-gray-500">No country matches that search.</li>}
          </ul>
        </div>
      )}
    </div>
  );
}

export default function SystemTestPage() {
  const stored = readStore();
  const [meta, setMeta] = useState(null);
  const [phase, setPhase] = useState('gate');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [country, setCountry] = useState(stored.country || '237');
  const [phoneLocal, setPhoneLocal] = useState(stored.phoneLocal || '');
  const [phone, setPhone] = useState(stored.phone || '');
  const [name, setName] = useState(stored.name || '');
  const [nameSource, setNameSource] = useState('');
  const [code, setCode] = useState('');
  const [replace, setReplace] = useState(false);
  const [hadServer, setHadServer] = useState(false);
  const [token, setToken] = useState(stored.token || '');
  const [formToken, setFormToken] = useState(stored.formToken || '');
  const [checks, setChecks] = useState(stored.checks || {});
  const [notes, setNotes] = useState(stored.notes || {});
  const [summary, setSummary] = useState(stored.summary || '');
  const [page, setPage] = useState(stored.page || 1);
  const [focusId, setFocusId] = useState('');
  const [login, setLogin] = useState(null);
  const [thanks, setThanks] = useState(null);
  const [rows, setRows] = useState([]);

  const pageCount = meta?.pages?.length || 1;
  const current = meta?.pages?.[page - 1];
  const progress = useMemo(() => {
    const total = meta?.total || 0;
    const answered = Object.values(checks).filter((value) => ['works', 'fails', 'skipped'].includes(value)).length;
    return { total, answered, percent: total ? Math.round((answered / total) * 100) : 0 };
  }, [checks, meta]);

  useEffect(() => {
    writeStore({ country, phoneLocal, phone, name, token, formToken, checks, notes, summary, page });
  }, [country, phoneLocal, phone, name, token, formToken, checks, notes, summary, page]);

  useEffect(() => {
    api('/meta').then(setMeta).catch((err) => setError(err.message));
    api('/session', { method: 'POST', body: { token: stored.token } })
      .then((json) => {
        setToken(json.token);
        setFormToken(json.formToken);
        if (json.expired) setNotice('The form was refreshed. Your answers in this browser are still here.');
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!focusId) return;
    const node = document.getElementById(`check-${focusId}`);
    if (node) node.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, [focusId, phase, page]);

  function payload() {
    return { checks, notes, summary, page, token, formToken, phone, tester_name: name };
  }

  async function refreshToken() {
    const json = await api('/session', { method: 'POST', body: { token } });
    setToken(json.token);
    setFormToken(json.formToken);
    if (json.expired) setNotice('The form was refreshed. Your answers in this browser are still here.');
    return json;
  }

  async function onLookup(intent) {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await refreshToken();
      const json = await api('/lookup', {
        method: 'POST',
        body: {
          intent,
          country_code: country,
          phone_local: phoneLocal,
          checks,
          notes,
          summary,
          tester_name: name,
          company_website: '',
        },
      });
      setPhone(json.phone);
      setNameSource(json.nameSource || '');
      if (json.suggestedName) setName(json.suggestedName);
      if (json.recoveredFromBrowser) setNotice('Answers saved in this browser were found and added to the test.');
      if (json.hadServer && intent === 'retrieve' && !json.recoveredFromBrowser) setNotice('Your saved test is ready. Enter the WhatsApp code.');
      setHadServer(Boolean(json.hadServer));
      setPhase(json.step === 'verify' ? 'verify' : 'name');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  async function onConfirmName(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const json = await api('/confirm-name', {
        method: 'POST',
        body: { phone, country_code: country, phone_local: phoneLocal, tester_name: name, checks, notes, summary },
      });
      setPhone(json.phone || phone);
      setPhase('verify');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  async function onVerify(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const json = await api('/verify', {
        method: 'POST',
        body: { phone, code, action: replace ? 'replace' : 'continue' },
      });
      setToken(json.token);
      setFormToken(json.formToken);
      setChecks(json.draft?.checks || {});
      setNotes(json.draft?.notes || {});
      setSummary(json.draft?.summary || '');
      setPage(json.page || 1);
      setLogin(json.login || null);
      setRows(json.rows || []);
      setPhase(json.review ? 'review' : 'test');
      if (json.review) {
        const review = await api('/save', { method: 'POST', token: json.token, body: { ...payload(), token: json.token, formToken: json.formToken, action: 'review', checks: json.draft?.checks || {}, notes: json.draft?.notes || {} } });
        setRows(review.rows || []);
        setPhase('review');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  async function save(action, extra = {}) {
    setBusy(true);
    setError('');
    try {
      const fresh = await refreshToken();
      const json = await api('/save', {
        method: 'POST',
        token: fresh.token,
        body: { ...payload(), ...extra, token: fresh.token, formToken: fresh.formToken, action },
      });
      if (json.formToken) setFormToken(json.formToken);
      if (json.draft) {
        setChecks(json.draft.checks || checks);
        setNotes(json.draft.notes || notes);
        setSummary(json.draft.summary ?? summary);
      }
      if (json.page) setPage(json.page);
      if (json.recovered) setNotice('The form token had expired. Your answers were kept and the form was refreshed.');
      if (json.step === 'review') {
        setRows(json.rows || []);
        setPhase('review');
      } else if (json.step === 'thanks') {
        setThanks(json);
        setPhase('thanks');
        localStorage.removeItem(STORE);
      } else if (action === 'later') {
        setNotice('Saved. You can close this page and retrieve the test with your WhatsApp number.');
      }
      return json;
    } catch (err) {
      if (err.status === 409) {
        setNotice(err.message);
        setPhase('gate');
      } else if (err.payload?.page) {
        setPage(err.payload.page);
        setError(err.message);
        if (err.payload.formToken) setFormToken(err.payload.formToken);
        if (err.payload.draft) setChecks(err.payload.draft.checks || checks);
      } else {
        setError(err.message);
      }
      return null;
    } finally {
      setBusy(false);
    }
  }

  function setAnswer(id, value) {
    setChecks((prev) => ({ ...prev, [id]: value }));
  }

  const fails = (thanks?.report?.rows || rows).filter((row) => row.result === 'fails');

  return (
    <TestChrome>
      <p className="text-xs font-bold tracking-[0.16em] uppercase text-[#8a6d1d]">System test</p>
      <h1 className="text-3xl md:text-4xl font-serif text-[#003D82] mt-1 mb-2">{COMPANY_NAME}</h1>
      <p className="text-gray-600 mb-4">Work through the real website. Do not delete real records, pay real money, or empty the database.</p>
      {error && <p className="mb-3 rounded-xl bg-red-50 text-red-800 px-4 py-3">{error}</p>}
      {notice && <p className="mb-3 rounded-xl bg-amber-50 text-amber-900 px-4 py-3">{notice}</p>}

      {phase === 'gate' && (
        <div className="grid md:grid-cols-2 gap-4">
          <form className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm" onSubmit={(event) => { event.preventDefault(); onLookup('start'); }}>
            <h2 className="text-2xl text-[#003D82] font-serif mb-3">Start a new test</h2>
            <label className="block text-sm font-semibold mb-1">Country</label>
            <CountryPicker countries={meta?.countries || []} value={country} onChange={setCountry} />
            <label className="block text-sm font-semibold mt-3 mb-1">WhatsApp number</label>
            <input className="w-full rounded-xl border border-[#e7e1d4] px-3 py-3" inputMode="numeric" placeholder="675321739" value={phoneLocal} onChange={(event) => setPhoneLocal(event.target.value)} />
            <p className="text-xs text-gray-500 mt-1">Enter the number without the country code.</p>
            <button disabled={busy} className="mt-4 w-full rounded-full bg-[#003D82] text-white font-semibold py-3">Continue</button>
          </form>
          <form className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm" onSubmit={(event) => { event.preventDefault(); onLookup('retrieve'); }}>
            <h2 className="text-2xl text-[#003D82] font-serif mb-3">Retrieve an application</h2>
            <p className="text-sm text-gray-600 mb-3">Use the same country and WhatsApp number. One saved test is kept for each number. Answers are not wiped unless you choose to start that number again.</p>
            <label className="block text-sm font-semibold mb-1">Country</label>
            <CountryPicker countries={meta?.countries || []} value={country} onChange={setCountry} />
            <label className="block text-sm font-semibold mt-3 mb-1">WhatsApp number</label>
            <input className="w-full rounded-xl border border-[#e7e1d4] px-3 py-3" inputMode="numeric" placeholder="675321739" value={phoneLocal} onChange={(event) => setPhoneLocal(event.target.value)} />
            <button disabled={busy} className="mt-4 w-full rounded-full border border-[#003D82] text-[#003D82] font-semibold py-3">Retrieve saved answers</button>
          </form>
        </div>
      )}

      {phase === 'name' && (
        <form className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm max-w-xl" onSubmit={onConfirmName}>
          <h2 className="text-2xl text-[#003D82] font-serif mb-2">Confirm your name</h2>
          <p className="text-sm text-gray-600 mb-3">
            {nameSource === 'whatsapp'
              ? 'This name came from the WhatsApp contact. Edit it or accept it.'
              : 'No name was found for this number. Type the name to use for the test.'}
          </p>
          <input className="w-full rounded-xl border border-[#e7e1d4] px-3 py-3" value={name} onChange={(event) => setName(event.target.value)} />
          <button disabled={busy} className="mt-4 rounded-full bg-[#003D82] text-white font-semibold px-6 py-3">Send WhatsApp code</button>
        </form>
      )}

      {phase === 'verify' && (
        <form className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm max-w-xl" onSubmit={onVerify}>
          <h2 className="text-2xl text-[#003D82] font-serif mb-2">Enter the 6-digit code</h2>
          <p className="text-sm text-gray-600 mb-3">The code was sent to {phone}. It expires in 10 minutes. After a few wrong tries, ask for a new code.</p>
          <input className="w-full rounded-xl border border-[#e7e1d4] px-3 py-3 tracking-[0.4em] text-center text-xl" inputMode="numeric" value={code} onChange={(event) => setCode(event.target.value)} />
          {hadServer && (
            <label className="mt-3 flex items-start gap-2 text-sm">
              <input type="checkbox" checked={replace} onChange={(event) => setReplace(event.target.checked)} />
              Start this number again and wipe the saved answers.
            </label>
          )}
          <div className="mt-4 flex gap-3">
            <button disabled={busy} className="rounded-full bg-[#003D82] text-white font-semibold px-6 py-3">Open the test</button>
            <button type="button" className="text-sm text-[#003D82] underline" onClick={() => api('/resend', { method: 'POST', body: { phone } }).then(() => setNotice('A new code was sent.')).catch((err) => setError(err.message))}>Send a new code</button>
          </div>
        </form>
      )}

      {(phase === 'test' || phase === 'review') && (
        <ProgressBar progress={progress} page={phase === 'review' ? pageCount : page} pageCount={pageCount} />
      )}

      {login?.password && phase !== 'gate' && phase !== 'thanks' && (
        <div className="mb-4 rounded-2xl border border-[#D4AF37] bg-white p-4">
          <p className="font-semibold text-[#003D82]">Test login</p>
          <p className="text-sm">Username: <strong>{login.username}</strong></p>
          <p className="text-sm">Password: <strong>{login.password}</strong></p>
          <p className="text-sm text-gray-600">{login.sent ? 'The same login was sent on WhatsApp.' : 'WhatsApp did not deliver the login. This is the only time the password is shown.'}</p>
        </div>
      )}
      {login && !login.password && login.existing && phase === 'test' && (
        <p className="mb-4 text-sm text-gray-600">This number already has an account ({login.username}). The password and role were not changed.</p>
      )}

      {phase === 'test' && current && (
        <div>
          {current.checks.map((item, index) => {
            const number = (page - 1) * 8 + index + 1;
            return (
              <article id={`check-${item.id}`} key={item.id} className="mb-4 rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm">
                <p className="text-xs font-bold text-[#8a6d1d]">Check {number}</p>
                <h2 className="text-xl text-[#003D82] font-serif mt-1">{item.text}</h2>
                <ol className="mt-3 space-y-2">
                  {item.steps.map((step, stepIndex) => (
                    <li key={step} className="flex gap-3 border-t border-[#f0ebe1] pt-2 first:border-0">
                      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#003D82] text-white text-sm font-bold">{stepIndex + 1}</span>
                      <span>{step}</span>
                    </li>
                  ))}
                </ol>
                <div className="mt-4 flex flex-wrap gap-2">
                  {[
                    ['works', 'Works'],
                    ['fails', 'Does not work'],
                    ['skipped', 'Not tested'],
                  ].map(([value, label]) => (
                    <button
                      type="button"
                      key={value}
                      onClick={() => setAnswer(item.id, value)}
                      className={`rounded-full px-4 py-2 text-sm font-semibold border ${checks[item.id] === value ? 'bg-[#003D82] text-white border-[#003D82]' : 'bg-white border-[#e7e1d4]'}`}
                    >
                      {label}
                    </button>
                  ))}
                </div>
                {checks[item.id] === 'fails' && (
                  <textarea
                    className="mt-3 w-full rounded-xl border border-[#e7e1d4] px-3 py-2"
                    rows={2}
                    placeholder="What failed?"
                    value={notes[item.id] || ''}
                    onChange={(event) => setNotes((prev) => ({ ...prev, [item.id]: event.target.value }))}
                  />
                )}
              </article>
            );
          })}
          <div className="flex flex-wrap gap-2">
            {page > 1 && <button type="button" className="rounded-full border px-4 py-2" onClick={() => save('prev')}>Back</button>}
            <button type="button" className="rounded-full border px-4 py-2" onClick={() => save('later')}>Save and continue later</button>
            {progress.percent === 100 && page < pageCount && <button type="button" className="rounded-full bg-[#D4AF37] text-[#003D82] font-bold px-4 py-2" onClick={() => save('review')}>Return to review</button>}
            {page < pageCount && <button type="button" className="rounded-full bg-[#003D82] text-white px-4 py-2" onClick={() => save('next')}>Next</button>}
            {page === pageCount && <button type="button" className="rounded-full bg-[#D4AF37] text-[#003D82] font-bold px-4 py-2" onClick={() => save('review')}>Review answers</button>}
          </div>
        </div>
      )}

      {phase === 'review' && (
        <div className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm">
          <h2 className="text-2xl text-[#003D82] font-serif mb-3">Review answers</h2>
          <ul className="divide-y">
            {(rows.length ? rows : []).map((row) => (
              <li key={row.id} className="py-3 flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{row.number}. {row.label}</p>
                  <p className="text-sm text-gray-700">{row.text}</p>
                  {row.note && <p className="text-sm text-gray-500 mt-1">{row.note}</p>}
                </div>
                <button
                  type="button"
                  className="shrink-0 rounded-full border border-[#003D82] text-[#003D82] px-3 py-1 text-sm"
                  onClick={() => {
                    setPage(row.page);
                    setFocusId(row.id);
                    setPhase('test');
                  }}
                >
                  Edit
                </button>
              </li>
            ))}
          </ul>
          <label className="block text-sm font-semibold mt-4">Overall note</label>
          <textarea className="w-full rounded-xl border border-[#e7e1d4] px-3 py-2" rows={3} value={summary} onChange={(event) => setSummary(event.target.value)} />
          <div className="mt-4 flex flex-wrap gap-2">
            <button type="button" className="rounded-full border px-4 py-2" onClick={() => setPhase('test')}>Back to questions</button>
            <button type="button" className="rounded-full bg-[#003D82] text-white font-semibold px-4 py-2" onClick={() => save('submit')}>Send the result</button>
          </div>
        </div>
      )}

      {phase === 'thanks' && thanks?.report && (
        <div className="rounded-2xl bg-white border border-[#e7e1d4] p-5 shadow-sm">
          <h2 className="text-2xl text-[#003D82] font-serif">Thank you</h2>
          <p className="mt-2">The result is saved. Works: {thanks.report.counts.works}. Does not work: {thanks.report.counts.fails}. Not tested: {thanks.report.counts.skipped}.</p>
          {!thanks.testerSent && <p className="mt-2">WhatsApp to {thanks.report.tester_phone} did not go out. The administrator can still open it from Help.</p>}
          <h3 className="mt-4 font-semibold">Does not work</h3>
          {fails.length === 0 && <p>Nothing was marked as not working.</p>}
          <ul className="list-disc pl-5">
            {fails.map((row) => (
              <li key={row.id} className="mt-1">{row.text}{row.note ? ` — ${row.note}` : ''}</li>
            ))}
          </ul>
        </div>
      )}
    </TestChrome>
  );
}
