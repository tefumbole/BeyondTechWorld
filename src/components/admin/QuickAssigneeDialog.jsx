import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Loader2, UserPlus } from 'lucide-react';
import { createUser } from '@/services/userService';
import { useToast } from '@/components/ui/use-toast';

function generateTempPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#';
  let pwd = '';
  for (let i = 0; i < 12; i += 1) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pwd;
}

const QuickAssigneeDialog = ({ open, onOpenChange, onCreated }) => {
  const { toast } = useToast();
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    full_name: '',
    email: '',
    phone: '',
    password: generateTempPassword(),
  });

  const resetForm = () => {
    setForm({
      full_name: '',
      email: '',
      phone: '',
      password: generateTempPassword(),
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.full_name.trim() || !form.email.trim() || !form.phone.trim()) {
      toast({ title: 'Required fields', description: 'Name, email, and phone are required.', variant: 'destructive' });
      return;
    }

    setBusy(true);
    try {
      const created = await createUser({
        full_name: form.full_name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        password: form.password,
        role: 'task_assignee',
      });

      const assignee = {
        id: created.id,
        name: created.full_name || created.name || form.full_name,
        full_name: created.full_name || created.name || form.full_name,
        email: created.email || form.email,
        phone: created.phone || form.phone,
        role: created.role || 'task_assignee',
        type: 'task_assignee',
      };

      toast({
        title: 'User created',
        description: `${assignee.name} was added. They will receive the task link on WhatsApp.`,
      });

      onCreated?.(assignee);
      resetForm();
      onOpenChange(false);
    } catch (err) {
      toast({ title: 'Could not create user', description: err.message, variant: 'destructive' });
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-[#003D82]">
            <UserPlus className="w-5 h-5" /> Create New Assignee
          </DialogTitle>
          <DialogDescription>
            Add someone who does not have an account yet. They will sign up via the task link if needed.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label>Full Name *</Label>
            <Input
              required
              value={form.full_name}
              onChange={(e) => setForm({ ...form, full_name: e.target.value })}
              placeholder="John Doe"
            />
          </div>
          <div>
            <Label>Email *</Label>
            <Input
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              placeholder="name@example.com"
            />
          </div>
          <div>
            <Label>WhatsApp Phone *</Label>
            <Input
              required
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
              placeholder="+237..."
            />
            <p className="text-xs text-gray-500 mt-1">
              No username or password needed. They will confirm via a WhatsApp OTP when they open the task link.
            </p>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
            <Button type="submit" disabled={busy} className="bg-[#003D82] hover:bg-[#002a5a]">
              {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Create & Add'}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default QuickAssigneeDialog;
