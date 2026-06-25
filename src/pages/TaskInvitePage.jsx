import React, { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { useToast } from '@/components/ui/use-toast';
import { useAuth } from '@/context/AuthContext';
import { fetchTaskInvite } from '@/services/registerService';
import { respondToTaskInvite } from '@/services/taskService';
import SignaturePadModal from '@/components/SignaturePadModal';
import { Loader2, CheckCircle, XCircle } from 'lucide-react';

const TaskInvitePage = () => {
  const { token } = useParams();
  const [searchParams] = useSearchParams();
  const action = searchParams.get('action');
  const navigate = useNavigate();
  const { toast } = useToast();
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [invite, setInvite] = useState(null);
  const [busy, setBusy] = useState(false);
  const [signatureOpen, setSignatureOpen] = useState(false);

  useEffect(() => {
    fetchTaskInvite(token)
      .then(async (data) => {
        setInvite(data.invite);
        if (data.loggedIn) {
          if (action === 'decline') {
            const res = await respondToTaskInvite(token, 'decline');
            if (res.success) {
              toast({ title: 'Task declined', description: res.taskTitle || 'Your response was recorded.' });
              navigate('/user/tasks/my-tasks', { replace: true });
              return;
            }
          }
          if (action === 'accept') {
            setSignatureOpen(true);
          } else {
            navigate(`/user/tasks/pending-acceptances?invite=${token}`, { replace: true });
          }
        }
      })
      .catch((err) => {
        toast({ title: 'Invalid link', description: err.message, variant: 'destructive' });
      })
      .finally(() => setLoading(false));
  }, [token, toast, navigate, action]);

  const buildLoginUrl = (respondAction) => {
    const username = (invite?.assignee_phone || '').replace(/\D/g, '');
    const redirect = `/task-invite/${token}?action=${respondAction}`;
    const params = new URLSearchParams({ redirect });
    if (username) {
      params.set('u', username);
      params.set('guest', '1');
    }
    return `/login?${params.toString()}`;
  };

  const respondNow = async (respondAction) => {
    if (!user) {
      navigate(buildLoginUrl(respondAction));
      return;
    }
    if (respondAction === 'accept') {
      setSignatureOpen(true);
      return;
    }
    setBusy(true);
    const res = await respondToTaskInvite(token, respondAction);
    setBusy(false);
    if (res.success) {
      toast({ title: 'Task declined' });
      navigate('/user/tasks/my-tasks');
    } else {
      toast({ title: 'Failed', description: res.error, variant: 'destructive' });
    }
  };

  const handleSignatureCapture = async (signatureDataUrl) => {
    setBusy(true);
    const res = await respondToTaskInvite(token, 'accept', signatureDataUrl);
    setBusy(false);
    if (res.success) {
      toast({
        title: 'Task accepted',
        description: res.alreadyAccepted
          ? 'You had already accepted this task.'
          : 'Your signature was recorded.',
      });
      navigate('/user/tasks/my-tasks', { replace: true });
    } else {
      toast({ title: 'Failed to accept', description: res.error, variant: 'destructive' });
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
          <div className="rounded-lg bg-blue-50 border border-blue-100 p-3 text-sm text-blue-900">
            Tap <strong>Accept Task</strong> to sign and confirm. We’ll take you to a quick sign-in first if needed
            (your username and temporary password are filled in for you).
          </div>

          <div className="flex gap-2">
            <Button
              type="button"
              className="flex-1 bg-green-600 hover:bg-green-700"
              disabled={busy}
              onClick={() => respondNow('accept')}
            >
              <CheckCircle className="w-4 h-4 mr-2" /> Accept Task
            </Button>
            <Button
              type="button"
              variant="outline"
              className="flex-1 text-red-600 border-red-200"
              disabled={busy}
              onClick={() => respondNow('decline')}
            >
              <XCircle className="w-4 h-4 mr-2" /> Reject Task
            </Button>
          </div>

          <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-800 flex gap-2">
            <CheckCircle className="w-4 h-4 mt-0.5 shrink-0" />
            <span>Your task portal shows <strong>Pending Tasks</strong> and <strong>My Tasks</strong> only.</span>
          </div>
        </CardContent>
      </Card>

      <SignaturePadModal
        isOpen={signatureOpen}
        onClose={() => setSignatureOpen(false)}
        onSignatureCapture={handleSignatureCapture}
        title="Sign to Accept Task"
        description="Draw your signature to confirm you accept this task assignment."
      />
    </div>
  );
};

export default TaskInvitePage;
