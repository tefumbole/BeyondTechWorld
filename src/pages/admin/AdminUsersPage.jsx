import React, { useEffect, useState } from 'react';
import { getAllUsers, createUser, updateUser, deleteUser } from '@/services/userService';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { useToast } from '@/components/ui/use-toast';
import { Plus, Search, Trash2, Edit, Loader2, User, Mail, Phone, Shield, Calendar, RefreshCcw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import UserManagementForm from '@/components/admin/UserManagementForm';
import { format } from 'date-fns';

const AdminUsersPage = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  
  // Modal State
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [formMode, setFormMode] = useState('create'); // 'create' | 'edit'
  const [selectedUser, setSelectedUser] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { toast } = useToast();

  const loadData = async () => {
    setLoading(true);
    try {
      const data = await getAllUsers();
      setUsers(data);
    } catch (e) {
      console.error("Failed to load users", e);
      toast({ 
        title: "Error", 
        description: "Failed to load user list. Please try again.", 
        variant: "destructive" 
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadData(); }, []);

  const filteredUsers = users.filter(u => 
    (u.full_name?.toLowerCase() || '').includes(search.toLowerCase()) ||
    (u.email?.toLowerCase() || '').includes(search.toLowerCase()) ||
    (u.role?.toLowerCase() || '').includes(search.toLowerCase())
  );

  const handleCreateOpen = () => {
    setFormMode('create');
    setSelectedUser(null);
    setIsFormOpen(true);
  };

  const handleEditOpen = (user) => {
    setFormMode('edit');
    setSelectedUser(user);
    setIsFormOpen(true);
  };

  const handleFormSubmit = async (formData) => {
    setIsSubmitting(true);
    try {
      if (formMode === 'create') {
        await createUser(formData);
        toast({ title: "Success", description: "User created successfully." });
      } else {
        await updateUser(selectedUser.id, formData);
        toast({ title: "Success", description: "User updated successfully." });
      }
      setIsFormOpen(false);
      loadData(); // Refresh list
    } catch (e) {
      toast({ 
        title: "Error", 
        description: e.message || "Operation failed.", 
        variant: "destructive" 
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("Are you sure you want to delete this user? This action cannot be undone.")) return;
    
    try {
      await deleteUser(id);
      toast({ title: "Deleted", description: "User removed successfully." });
      loadData();
    } catch (e) {
      toast({ title: "Error", description: "Failed to delete user.", variant: "destructive" });
    }
  };

  const getRoleBadgeColor = (role) => {
    const r = (role || '').toLowerCase();
    switch(r) {
      case 'super_admin': return 'bg-red-100 text-red-800 border-red-200';
      case 'admin': return 'bg-purple-100 text-purple-800 border-purple-200';
      case 'director': return 'bg-red-100 text-red-800 border-red-200';
      case 'manager': return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'student': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'shareholder': return 'bg-green-100 text-green-800 border-green-200';
      case 'applicant': return 'bg-gray-100 text-gray-800 border-gray-200';
      default: return 'bg-gray-50 text-gray-600 border-gray-200';
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 className="text-3xl font-bold text-[#003D82]">User Management</h1>
            <p className="text-gray-500">Manage all system users, roles, and access.</p>
        </div>
        <div className="flex gap-2">
            <Button variant="outline" onClick={loadData} disabled={loading}>
                <RefreshCcw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                Refresh
            </Button>
            <Button className="bg-[#003D82] hover:bg-[#002e63] shadow-sm" onClick={handleCreateOpen}>
              <Plus className="mr-2 h-4 w-4" /> Create User
            </Button>
        </div>
      </div>

      <div className="flex flex-col sm:flex-row gap-4 items-center bg-white p-4 rounded-lg shadow-sm border border-gray-100">
        <div className="relative w-full">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <Input 
                placeholder="Search by name, email, or role..." 
                className="pl-9" 
                value={search} 
                onChange={e => setSearch(e.target.value)} 
            />
        </div>
      </div>

      <Card className="border-gray-200 shadow-sm overflow-hidden">
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 text-gray-600 border-b">
                <tr>
                  <th className="p-4 font-semibold">User Details</th>
                  <th className="p-4 font-semibold">Contact</th>
                  <th className="p-4 font-semibold">Role</th>
                  <th className="p-4 font-semibold">Joined</th>
                  <th className="p-4 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {loading ? (
                  <tr>
                    <td colSpan="5" className="p-12 text-center text-gray-500">
                      <Loader2 className="w-8 h-8 animate-spin mx-auto mb-3 text-[#003D82]" />
                      <p>Loading users...</p>
                    </td>
                  </tr>
                ) : filteredUsers.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="p-12 text-center text-gray-500">
                      <User className="w-8 h-8 mx-auto mb-3 opacity-20" />
                      <p>No users found matching your search.</p>
                    </td>
                  </tr>
                ) : (
                  filteredUsers.map(u => (
                    <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                      <td className="p-4">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-[#003D82] font-bold">
                            {u.full_name ? u.full_name.charAt(0).toUpperCase() : '?'}
                          </div>
                          <div>
                            <div className="font-semibold text-gray-900">{u.full_name || 'No Name'}</div>
                            <div className="text-xs text-gray-500">ID: {u.id.slice(0, 8)}...</div>
                          </div>
                        </div>
                      </td>
                      <td className="p-4">
                        <div className="flex flex-col gap-1">
                          <div className="flex items-center gap-2 text-gray-700">
                            <Mail className="w-3.5 h-3.5 text-gray-400" />
                            <span>{u.email}</span>
                          </div>
                          {u.phone && (
                            <div className="flex items-center gap-2 text-gray-500 text-xs">
                              <Phone className="w-3.5 h-3.5 text-gray-400" />
                              <span>{u.phone}</span>
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="p-4">
                        <Badge variant="outline" className={`${getRoleBadgeColor(u.role)} font-medium capitalize`}>
                          {u.role || 'User'}
                        </Badge>
                      </td>
                      <td className="p-4 text-gray-500">
                        <div className="flex items-center gap-2">
                           <Calendar className="w-3.5 h-3.5 text-gray-400" />
                           {u.created_at ? format(new Date(u.created_at), 'MMM d, yyyy') : 'N/A'}
                        </div>
                      </td>
                      <td className="p-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            className="h-8 w-8 p-0 text-blue-600 hover:text-blue-800 hover:bg-blue-50"
                            onClick={() => handleEditOpen(u)}
                          >
                            <Edit className="w-4 h-4" />
                          </Button>
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            onClick={() => handleDelete(u.id)}
                            className="h-8 w-8 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                          >
                            <Trash2 className="w-4 h-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <UserManagementForm 
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        mode={formMode}
        initialData={selectedUser}
        onSubmit={handleFormSubmit}
        isSubmitting={isSubmitting}
      />
    </div>
  );
};

export default AdminUsersPage;