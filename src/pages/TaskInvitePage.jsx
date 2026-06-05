import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { useToast } from '@/components/ui/use-toast';
import { fetchTaskInvite, requestRegistration, verifyRegistration } from '@/services/registerService';
import { Loader2, CheckCircle, LogIn, UserPlus } from 'lucide-react';

const TaskInvitePage = () => {
  const { token } = useParams();
  const navigate = useNavigate();
  const { toast } = useToast();
  const [loading, setLoading] = useState(true);
  const [invite, setInvite] = useState(null);
  const [mode, setMode] = useState('login');
  const [step, setStep] = useState('form');
  const [pendingId, setPendingId] = useState(null);
  const [otp, setOtp] = useState('');
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ full_name: '', email: '', phone: '', password: '' });

  useEffect(() => {
    fetchTaskInvite(token)
      .then((data) => {
        setInvite(data.invite);
        setForm((prev) => ({
          ...prev,
          full_name: data.invite?.assignee_name || '',
          email: data.invite?.assignee_email || '',
          phone: data.invite?.assignee_phone || '',
        }));
      })
      .catch((err) => {
        toast({ title: 'Invalid link', description: err.message, variant: 'destructive' });
      })
      .finally(() => setLoading(false));
  }, [token, toast]);

  const handleRegister = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const result = await requestRegistration({ ...form, inviteToken: token });
      setPendingId(result.pendingId);
      setStep('otp');
      toast({ title: 'OTP Sent', description: `Code sent to ${result.maskedPhone}` });
    } catch (err) {
      toast({ title: 'Registration failed', description: err.message, variant: 'destructive' });
    } finally {
      setBusy(false);
    }
  };

  const handleVerify = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      await verifyRegistration({ pendingId, otp });
      toast({ title: 'Account created', description: 'Sign in to view your assigned task.' });
      navigate(`/login?redirect=/user/tasks/pending-acceptances&invite=${token}`);
    } catch (err) {
      toast({ title: 'Verification failed', description: err.message, variant: 'destructive' });
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <Loader2 className="w-8 h-8 animate-spin text-[#003D82]" />
      </div>
    );
  }

  if (!invite) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
        <Card className="max-w-md w-full">
          <CardContent className="pt-6 text-center text-gray-600">
            This task invite link is invalid or has expired.
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <Card className="max-w-lg w-full shadow-lg">
        <CardHeader>
          <CardTitle className="text-[#003D82]">Task Assignment</CardTitle>
          <CardDescription>
            You have been assigned: <strong>{invite.title}</strong>
            {invite.deadline && <> · Deadline {new Date(invite.deadline).toLocaleDateString()}</>}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex gap-2">
            <Button
              type="button"
              variant={mode === 'login' ? 'default' : 'outline'}
              className={mode === 'login' ? 'bg-[#003D82]' : ''}
              onClick={() => setMode('login')}
            >
              <LogIn className="w-4 h-4 mr-2" /> I have an account
            </Button>
            <Button
              type="button"
              variant={mode === 'register' ? 'default' : 'outline'}
              className={mode === 'register' ? 'bg-[#003D82]' : ''}
              onClick={() => setMode('register')}
            >
              <UserPlus className="w-4 h-4 mr-2" /> Create account
            </Button>
          </div>

          {mode === 'login' ? (
            <div className="space-y-3">
              <p className="text-sm text-gray-600">
                Sign in with your existing Alpha Bridge account to open your task dashboard and accept this assignment.
              </p>
              <Button asChild className="w-full bg-[#003D82]">
                <Link to={`/login?redirect=/user/tasks/pending-acceptances&invite=${token}`}>
                  Continue to Sign In
                </Link>
              </Button>
            </div>
          ) : step === 'form' ? (
            <form onSubmit={handleRegister} className="space-y-4">
              <p className="text-sm text-gray-600">
                Create a staff account (OTP confirmation via WhatsApp). You will get access to My Tasks after verification.
              </p>
              <div>
                <Label>Full Name</Label>
                <Input required value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} />
              </div>
              <div>
                <Label>Email</Label>
                <Input type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
              </div>
              <div>
                <Label>WhatsApp Phone</Label>
                <Input required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+237..." />
              </div>
              <div>
                <Label>Password</Label>
                <Input type="password" required minLength={8} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
              </div>
              <Button type="submit" disabled={busy} className="w-full bg-[#003D82]">
                {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Send OTP & Continue'}
              </Button>
            </form>
          ) : (
            <form onSubmit={handleVerify} className="space-y-4">
              <p className="text-sm text-gray-600">Enter the 6-digit code sent to your WhatsApp.</p>
              <Input
                value={otp}
                onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="123456"
                className="text-center text-2xl tracking-widest"
              />
              <Button type="submit" disabled={busy || otp.length !== 6} className="w-full bg-[#003D82]">
                {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Verify & Create Account'}
              </Button>
            </form>
          )}

          <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-800 flex gap-2">
            <CheckCircle className="w-4 h-4 mt-0.5 shrink-0" />
            <span>Staff accounts can access <strong>My Tasks</strong> and <strong>Pending Acceptances</strong> from the main menu after login.</span>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default TaskInvitePage;
