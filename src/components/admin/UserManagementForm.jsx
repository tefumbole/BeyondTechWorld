import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2 } from 'lucide-react';

const UserManagementForm = ({ isOpen, onClose, mode, initialData, onSubmit, isSubmitting, audience = 'user' }) => {
  const isCustomer = audience === 'customer';
  const [formData, setFormData] = React.useState({
    email: '',
    username: '',
    full_name: '',
    phone: '',
    role: 'guest',
    password: '',
    confirmPassword: '',
    changePassword: false,
  });
  const [formError, setFormError] = React.useState('');

  React.useEffect(() => {
    if (mode === 'edit' && initialData) {
      setFormData({
        email: initialData.email || '',
        username: initialData.username || '',
        full_name: initialData.full_name || '',
        phone: initialData.phone || '',
        role: initialData.role || initialData.primary_role || 'guest',
        password: '',
        confirmPassword: '',
        changePassword: false,
      });
    } else {
      setFormData({
        email: '',
        username: '',
        full_name: '',
        phone: '',
        role: isCustomer ? 'customer' : 'guest',
        password: '',
        confirmPassword: '',
        changePassword: false,
      });
    }
    setFormError('');
  }, [mode, initialData, isOpen, isCustomer]);

  const roles = [
    { value: 'super_admin', label: 'Super Admin' },
    { value: 'admin', label: 'Admin' },
    { value: 'director', label: 'Director' },
    { value: 'manager', label: 'Manager' },
    { value: 'student', label: 'Student' },
    { value: 'shareholder', label: 'Shareholder' },
    { value: 'applicant', label: 'Applicant' },
    { value: 'guest', label: 'Guest' },
  ];

  const handleSubmit = (e) => {
    e.preventDefault();
    setFormError('');

    if (mode === 'create' && !isCustomer) {
      if (!formData.password || formData.password.length < 8) {
        setFormError('Password must be at least 8 characters.');
        return;
      }
      if (formData.password !== formData.confirmPassword) {
        setFormError('Passwords do not match.');
        return;
      }
      if (formData.username && formData.username.length < 3) {
        setFormError('Username must be at least 3 characters.');
        return;
      }
    }

    if (mode === 'edit' && formData.changePassword) {
      if (!formData.password || formData.password.length < 8) {
        setFormError('New password must be at least 8 characters.');
        return;
      }
      if (formData.password !== formData.confirmPassword) {
        setFormError('Passwords do not match.');
        return;
      }
    }

    const payload = {
      email: formData.email.trim(),
      username: isCustomer ? null : (formData.username.trim() || null),
      full_name: formData.full_name.trim(),
      phone: formData.phone.trim() || null,
      role: isCustomer ? 'customer' : formData.role,
    };

    if (mode === 'create' && !isCustomer) {
      payload.password = formData.password;
    } else if (mode === 'edit' && formData.changePassword && formData.password) {
      payload.password = formData.password;
    }

    onSubmit(payload);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {mode === 'edit' ? 'Edit User' : (isCustomer ? 'Add Customer' : 'Create New User')}
          </DialogTitle>
          <DialogDescription>
            {mode === 'edit'
              ? 'Update user information, role, or password.'
              : isCustomer
                ? 'Add a customer contact. They confirm via WhatsApp OTP — no username or password needed.'
                : 'Set email, username, password, and role. Users can sign in with email or username.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {formError && (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2">{formError}</p>
          )}

          <div className="space-y-2">
            <Label htmlFor="email">Email Address *</Label>
            <Input
              id="email"
              type="email"
              placeholder="user@example.com"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              disabled={mode === 'edit'}
              required
              className={mode === 'edit' ? 'bg-gray-100' : ''}
            />
          </div>

          {!isCustomer && (
            <div className="space-y-2">
              <Label htmlFor="username">Username</Label>
              <Input
                id="username"
                placeholder="j.doe"
                value={formData.username}
                onChange={(e) => setFormData({ ...formData, username: e.target.value })}
              />
              <p className="text-xs text-gray-500">Optional. Used for login instead of email.</p>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="full_name">Full Name *</Label>
            <Input
              id="full_name"
              placeholder="John Doe"
              value={formData.full_name}
              onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="phone">Phone Number</Label>
            <Input
              id="phone"
              type="tel"
              placeholder="+237 6XX XXX XXX"
              value={formData.phone}
              onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
            />
            <p className="text-xs text-gray-500">Required for WhatsApp OTP login and password reset.</p>
          </div>

          {isCustomer ? null : mode === 'create' ? (
            <>
              <div className="space-y-2">
                <Label htmlFor="password">Password *</Label>
                <Input
                  id="password"
                  type="password"
                  placeholder="Minimum 8 characters"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirmPassword">Confirm Password *</Label>
                <Input
                  id="confirmPassword"
                  type="password"
                  value={formData.confirmPassword}
                  onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                  required
                />
              </div>
            </>
          ) : (
            <div className="space-y-3 rounded-lg border p-3 bg-gray-50">
              <div className="flex items-center gap-2">
                <Checkbox
                  id="changePassword"
                  checked={formData.changePassword}
                  onCheckedChange={(checked) =>
                    setFormData({ ...formData, changePassword: Boolean(checked), password: '', confirmPassword: '' })
                  }
                />
                <Label htmlFor="changePassword" className="cursor-pointer">Change password</Label>
              </div>
              {formData.changePassword && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="password">New Password</Label>
                    <Input
                      id="password"
                      type="password"
                      placeholder="Minimum 8 characters"
                      value={formData.password}
                      onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="confirmPassword">Confirm New Password</Label>
                    <Input
                      id="confirmPassword"
                      type="password"
                      value={formData.confirmPassword}
                      onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                    />
                  </div>
                </>
              )}
            </div>
          )}

          {!isCustomer && (
            <div className="space-y-2">
              <Label htmlFor="role">Role *</Label>
              <Select value={formData.role} onValueChange={(value) => setFormData({ ...formData, role: value })}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                      {role.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="flex gap-2 justify-end pt-4">
            <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting} className="bg-[#003D82] hover:bg-[#002e63]">
              {isSubmitting ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  {mode === 'create' ? 'Creating...' : 'Saving...'}
                </>
              ) : (
                mode === 'edit' ? 'Save Changes' : (isCustomer ? 'Add Customer' : 'Create User')
              )}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
};

export default UserManagementForm;
