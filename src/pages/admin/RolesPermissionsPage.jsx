import React, { useState, useEffect } from 'react';
import { 
  getAllUsersWithRoles, 
  getAllRoles, 
  assignRoleToUser, 
  formatRoleLabel, 
  getRolePermissions,
  createRole,
  createUserWithRole,
  deleteRole,
  addPermissionToRole,
  removePermissionFromRole
} from '@/services/roleService';
import { getPermissionsByCategory, getAllPermissionIds, roleNameToSlug } from '@/config/permissionCatalog';
import { useToast } from '@/components/ui/use-toast';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Search, Shield, Users, Plus, Trash2, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';

const PERMISSIONS_BY_CATEGORY = getPermissionsByCategory();

function isImmutableRole(roleName) {
  return roleNameToSlug(roleName) === 'super_admin';
}

const CreateRoleModal = ({ onSuccess }) => {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const { toast } = useToast();

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!name.trim()) {
      toast({ title: 'Error', description: 'Role name is required', variant: 'destructive' });
      return;
    }

    setLoading(true);
    try {
      const result = await createRole(name.trim(), description.trim());
      
      if (result.success) {
        toast({ title: 'Success', description: 'Role created successfully' });
        setOpen(false);
        setName('');
        setDescription('');
        if (onSuccess) onSuccess();
      } else {
        toast({ title: 'Error', description: result.error, variant: 'destructive' });
      }
    } catch (error) {
      toast({ title: 'Error', description: 'Failed to create role', variant: 'destructive' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="bg-[#003D82] hover:bg-[#003D82]/90">
          <Plus className="w-4 h-4 mr-2" /> Create Role
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Create New Role</DialogTitle>
          <DialogDescription>Add a new role to the system with custom permissions.</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <div className="space-y-4">
            <div>
              <Label htmlFor="role-name">Role Name *</Label>
              <Input
                id="role-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g., Manager"
                required
              />
            </div>
            <div>
              <Label htmlFor="role-description">Description</Label>
              <Textarea
                id="role-description"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Describe the role's responsibilities..."
                rows={3}
              />
            </div>
          </div>
          <DialogFooter className="mt-6">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={loading} className="bg-[#003D82] hover:bg-[#003D82]/90">
              {loading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : null}
              Create Role
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

const CreateUserModal = ({ roles, onSuccess }) => {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fullName, setFullName] = useState('');
  const [roleName, setRoleName] = useState('');
  const { toast } = useToast();

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!email.trim() || !password.trim() || !fullName.trim() || !roleName) {
      toast({ title: 'Error', description: 'All fields are required', variant: 'destructive' });
      return;
    }

    setLoading(true);
    try {
      const result = await createUserWithRole(email, password, fullName, roleName);
      
      if (result.success) {
        toast({ title: 'Success', description: 'User created successfully' });
        setOpen(false);
        setEmail('');
        setPassword('');
        setFullName('');
        setRoleName('');
        if (onSuccess) onSuccess();
      } else {
        toast({ title: 'Error', description: result.error, variant: 'destructive' });
      }
    } catch (error) {
      toast({ title: 'Error', description: 'Failed to create user', variant: 'destructive' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="bg-green-600 hover:bg-green-700 text-white">
          <Plus className="w-4 h-4 mr-2" /> Create User
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Create New User</DialogTitle>
          <DialogDescription>Add a new user to the system with a specific role.</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit}>
          <div className="space-y-4">
            <div>
              <Label htmlFor="user-fullname">Full Name *</Label>
              <Input
                id="user-fullname"
                value={fullName}
                onChange={(e) => setFullName(e.target.value)}
                placeholder="John Doe"
                required
              />
            </div>
            <div>
              <Label htmlFor="user-email">Email *</Label>
              <Input
                id="user-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="user@example.com"
                required
              />
            </div>
            <div>
              <Label htmlFor="user-password">Password *</Label>
              <Input
                id="user-password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Minimum 6 characters"
                required
                minLength={6}
              />
            </div>
            <div>
              <Label htmlFor="user-role">Role *</Label>
              <Select value={roleName} onValueChange={setRoleName} required>
                <SelectTrigger>
                  <SelectValue placeholder="Select a role" />
                </SelectTrigger>
                <SelectContent>
                  {roles.map(r => (
                    <SelectItem key={r.id} value={r.name}>
                      {formatRoleLabel(r.name)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter className="mt-6">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={loading} className="bg-green-600 hover:bg-green-700">
              {loading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : null}
              Create User
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

const PermissionsEditor = ({ role, onUpdate }) => {
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [updating, setUpdating] = useState(false);
  const [fetchError, setFetchError] = useState(null);
  const { toast } = useToast();
  const roleLocked = isImmutableRole(role?.name);

  useEffect(() => {
    if (!role?.name) {
      setPermissions([]);
      return;
    }

    let cancelled = false;

    const load = async () => {
      setLoading(true);
      setFetchError(null);
      try {
        const result = await getRolePermissions(role.name);
        if (cancelled) return;

        if (result.success) {
          setPermissions(result.data || []);
        } else {
          setPermissions([]);
          setFetchError(result.error || 'Failed to load permissions');
        }
      } catch (error) {
        if (!cancelled) {
          setPermissions([]);
          setFetchError(error.message || 'Failed to load permissions');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [role?.id, role?.name]);

  const handleTogglePermission = async (permissionId, shouldEnable) => {
    if (roleLocked) return;

    setUpdating(true);
    const previous = permissions;
    setPermissions((current) => {
      if (shouldEnable) {
        return current.includes(permissionId) ? current : [...current, permissionId];
      }
      return current.filter((id) => id !== permissionId);
    });

    try {
      const result = shouldEnable
        ? await addPermissionToRole(role.name, permissionId)
        : await removePermissionFromRole(role.name, permissionId);

      if (!result.success) {
        setPermissions(previous);
        toast({ title: 'Error', description: result.error || 'Failed to update permission', variant: 'destructive' });
        return;
      }

      toast({ title: 'Success', description: 'Permission updated' });
      if (onUpdate) onUpdate();
    } catch (error) {
      setPermissions(previous);
      toast({ title: 'Error', description: error.message || 'Failed to update permission', variant: 'destructive' });
    } finally {
      setUpdating(false);
    }
  };

  const handleToggleCategory = async (categoryPermIds, checked) => {
    if (roleLocked) return;

    setUpdating(true);
    const previous = permissions;
    setPermissions((current) => {
      const next = new Set(current);
      for (const permId of categoryPermIds) {
        if (checked) next.add(permId);
        else next.delete(permId);
      }
      return [...next];
    });

    try {
      for (const permId of categoryPermIds) {
        const isChecked = previous.includes(permId);
        if (checked && !isChecked) {
          const result = await addPermissionToRole(role.name, permId);
          if (!result.success) throw new Error(result.error || 'Failed to add permission');
        } else if (!checked && isChecked) {
          const result = await removePermissionFromRole(role.name, permId);
          if (!result.success) throw new Error(result.error || 'Failed to remove permission');
        }
      }
      toast({ title: 'Success', description: checked ? 'Category permissions enabled' : 'Category permissions cleared' });
      if (onUpdate) onUpdate();
    } catch (error) {
      setPermissions(previous);
      toast({ title: 'Error', description: error.message || 'Failed to update category permissions', variant: 'destructive' });
    } finally {
      setUpdating(false);
    }
  };

  const handleToggleAll = async (checked) => {
    if (roleLocked) return;

    const allIds = getAllPermissionIds();
    setUpdating(true);
    const previous = permissions;
    setPermissions(checked ? [...allIds] : []);

    try {
      for (const permId of allIds) {
        const isChecked = previous.includes(permId);
        if (checked && !isChecked) {
          const result = await addPermissionToRole(role.name, permId);
          if (!result.success) throw new Error(result.error || 'Failed to add permission');
        } else if (!checked && isChecked) {
          const result = await removePermissionFromRole(role.name, permId);
          if (!result.success) throw new Error(result.error || 'Failed to remove permission');
        }
      }
      toast({ title: 'Success', description: checked ? 'All permissions enabled' : 'All permissions cleared' });
      if (onUpdate) onUpdate();
    } catch (error) {
      setPermissions(previous);
      toast({ title: 'Error', description: error.message || 'Failed to update permissions', variant: 'destructive' });
    } finally {
      setUpdating(false);
    }
  };

  const allPermissionIds = getAllPermissionIds();
  const allChecked = allPermissionIds.length > 0 && allPermissionIds.every((id) => permissions.includes(id));
  const someChecked = allPermissionIds.some((id) => permissions.includes(id));

  if (loading) {
    return (
      <div className="flex justify-center py-10">
        <Loader2 className="w-6 h-6 animate-spin text-[#003D82]" />
      </div>
    );
  }

  return (
    <div className="space-y-6 max-h-[60vh] overflow-y-auto pr-2">
      {fetchError && (
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-md p-3">{fetchError}</p>
      )}
      {roleLocked && (
        <p className="text-sm text-gray-600 bg-gray-100 border rounded-md p-3">
          Super Admin always has full access. Permissions for this role cannot be edited.
        </p>
      )}
      {!roleLocked && (
        <div className="flex items-center gap-3 p-3 border rounded-lg bg-blue-50 border-blue-200">
          <Checkbox
            id={`perm-all-${role.id}`}
            checked={allChecked}
            onCheckedChange={(checked) => handleToggleAll(checked === true)}
            disabled={updating}
          />
          <Label htmlFor={`perm-all-${role.id}`} className="cursor-pointer font-semibold text-[#003D82]">
            Select all permissions for this role
            {someChecked && !allChecked ? ' (partial)' : ''}
          </Label>
        </div>
      )}
      {Object.entries(PERMISSIONS_BY_CATEGORY).map(([category, perms]) => {
        const categoryPermIds = perms.map((p) => p.id);
        const categoryAllChecked = categoryPermIds.every((id) => permissions.includes(id));
        const categorySomeChecked = categoryPermIds.some((id) => permissions.includes(id));

        return (
        <div key={category} className="border rounded-lg p-4 bg-white">
          <div className="flex items-center gap-3 mb-3">
            <Checkbox
              id={`perm-cat-${role.id}-${category}`}
              checked={categoryAllChecked}
              onCheckedChange={(checked) => handleToggleCategory(categoryPermIds, checked === true)}
              disabled={updating || roleLocked}
            />
            <Label
              htmlFor={`perm-cat-${role.id}-${category}`}
              className="text-sm font-bold text-[#003D82] uppercase tracking-wide cursor-pointer"
            >
              {category}
              {categorySomeChecked && !categoryAllChecked ? ' (partial)' : ''}
            </Label>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {perms.map((perm) => {
              const isChecked = permissions.includes(perm.id);
              return (
                <div
                  key={perm.id}
                  className="flex items-start gap-3 p-2 rounded-md hover:bg-gray-50 transition-colors"
                >
                  <Checkbox
                    id={`perm-${role.id}-${perm.id}`}
                    checked={isChecked}
                    onCheckedChange={(checked) => handleTogglePermission(perm.id, checked === true)}
                    disabled={updating || roleLocked}
                    className="mt-0.5"
                  />
                  <Label
                    htmlFor={`perm-${role.id}-${perm.id}`}
                    className="flex-1 cursor-pointer text-sm text-gray-900 leading-snug"
                  >
                    {perm.label}
                  </Label>
                  {isChecked && <Check className="w-4 h-4 text-green-500 shrink-0" />}
                </div>
              );
            })}
          </div>
        </div>
        );
      })}
    </div>
  );
};

const RolesPermissionsPage = () => {
  const [users, setUsers] = useState([]);
  const [filteredUsers, setFilteredUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [updatingUserId, setUpdatingUserId] = useState(null);
  const [selectedRole, setSelectedRole] = useState(null);
  const { toast } = useToast();

  useEffect(() => {
    fetchData();
  }, []);

  useEffect(() => {
    if (searchTerm) {
      setFilteredUsers(users.filter(u => 
        (u.email?.toLowerCase().includes(searchTerm.toLowerCase())) || 
        (u.full_name?.toLowerCase().includes(searchTerm.toLowerCase()))
      ));
    } else {
      setFilteredUsers(users);
    }
  }, [searchTerm, users]);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [usersRes, rolesRes] = await Promise.all([
        getAllUsersWithRoles(),
        getAllRoles()
      ]);
      
      if (usersRes.success) {
        setUsers(usersRes.data || []);
        setFilteredUsers(usersRes.data || []);
      }
      
      if (rolesRes.success) {
        setRoles(rolesRes.data || []);
        setSelectedRole((prev) => {
          if (prev && rolesRes.data?.some((r) => r.id === prev.id)) return prev;
          return rolesRes.data?.[0] || null;
        });
      } else if (rolesRes.error) {
        toast({ title: 'Error', description: rolesRes.error, variant: 'destructive' });
      }
    } catch (error) {
      console.error("Error fetching data:", error);
      toast({ 
        title: 'Error', 
        description: 'Failed to load roles and users.', 
        variant: 'destructive' 
      });
    } finally {
      setLoading(false);
    }
  };

  const handleRoleChange = async (userId, newRoleName) => {
    setUpdatingUserId(userId);
    try {
      const res = await assignRoleToUser(userId, newRoleName);
      
      if (res.success) {
        toast({ title: 'Success', description: 'Role updated successfully' });
        await fetchData();
      } else {
        toast({ title: 'Error', description: res.error, variant: 'destructive' });
      }
    } catch (error) {
      toast({ title: 'Error', description: 'An unexpected error occurred.', variant: 'destructive' });
    } finally {
      setUpdatingUserId(null);
    }
  };

  const handleDeleteRole = async (roleId) => {
    if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
      return;
    }

    try {
      const result = await deleteRole(roleId);
      if (result.success) {
        toast({ title: 'Success', description: 'Role deleted successfully' });
        await fetchData();
        if (selectedRole?.id === roleId) {
          setSelectedRole(roles[0] || null);
        }
      } else {
        toast({ title: 'Error', description: result.error, variant: 'destructive' });
      }
    } catch (error) {
      toast({ title: 'Error', description: 'Failed to delete role', variant: 'destructive' });
    }
  };

  const getRoleBadgeColor = (roleName) => {
    if (!roleName) return 'bg-gray-100 text-gray-800 border-gray-200';
    const name = roleName.toLowerCase();
    if (name.includes('super') || name.includes('admin')) return 'bg-purple-100 text-purple-800 border-purple-200';
    if (name.includes('director') || name.includes('manager')) return 'bg-blue-100 text-blue-800 border-blue-200';
    if (name.includes('staff')) return 'bg-green-100 text-green-800 border-green-200';
    return 'bg-gray-100 text-gray-800 border-gray-200';
  };

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-[#003D82]">Roles & Permissions</h1>
        <p className="text-gray-500 mt-1">Manage user access levels and system permissions.</p>
      </div>

      <Tabs defaultValue="users" className="w-full">
        <TabsList className="mb-4">
          <TabsTrigger value="users" className="flex items-center gap-2">
            <Users className="w-4 h-4" /> User Roles
          </TabsTrigger>
          <TabsTrigger value="permissions" className="flex items-center gap-2">
            <Shield className="w-4 h-4" /> Role Permissions
          </TabsTrigger>
        </TabsList>

        <TabsContent value="users">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>User Role Assignments</CardTitle>
                  <CardDescription>Assign specific roles to users to grant them access to different parts of the system.</CardDescription>
                </div>
                <div className="flex gap-2">
                  <CreateRoleModal onSuccess={fetchData} />
                  <CreateUserModal roles={roles} onSuccess={fetchData} />
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="flex items-center mb-4 max-w-sm relative">
                <Search className="w-4 h-4 absolute left-3 text-gray-400" />
                <Input 
                  placeholder="Search users..." 
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-9"
                />
              </div>

              <div className="border rounded-md overflow-hidden">
                <Table>
                  <TableHeader className="bg-gray-50">
                    <TableRow>
                      <TableHead>User</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead>Current Role</TableHead>
                      <TableHead>Assign New Role</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {loading ? (
                      <TableRow>
                        <TableCell colSpan={4} className="text-center py-8">
                          <Loader2 className="w-6 h-6 animate-spin mx-auto text-[#003D82]" />
                        </TableCell>
                      </TableRow>
                    ) : filteredUsers.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={4} className="text-center py-8 text-gray-500">
                          {searchTerm ? 'No users found matching your search.' : 'No users found. Create a user to assign roles.'}
                        </TableCell>
                      </TableRow>
                    ) : (
                      filteredUsers.map(u => (
                        <TableRow key={u.id}>
                          <TableCell className="font-medium text-gray-900">{u.full_name || 'N/A'}</TableCell>
                          <TableCell className="text-gray-900">{u.email}</TableCell>
                          <TableCell>
                            <Badge variant="outline" className={getRoleBadgeColor(u.role_name)}>
                              {u.role_name ? formatRoleLabel(u.role_name) : 'No Role'}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2 max-w-[200px]">
                              <Select 
                                value={u.role_name || ''} 
                                onValueChange={(val) => handleRoleChange(u.id, val)}
                                disabled={updatingUserId === u.id}
                              >
                                <SelectTrigger>
                                  <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                  {roles.map(r => (
                                    <SelectItem key={r.id} value={r.name}>
                                      {formatRoleLabel(r.name)}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                              {updatingUserId === u.id && (
                                <Loader2 className="w-4 h-4 animate-spin text-[#003D82]" />
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="permissions">
          <Card>
            <CardHeader>
              <CardTitle>Role Permission Matrix</CardTitle>
              <CardDescription>Configure which permissions each role has access to within the system.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <div className="lg:col-span-1 space-y-2">
                  <h3 className="font-semibold text-gray-900 mb-3 text-sm uppercase tracking-wider">Select Role</h3>
                  <div className="space-y-2">
                    {roles.length === 0 ? (
                      <p className="text-sm text-gray-500">No roles yet. Create one to get started.</p>
                    ) : (
                      roles.map((r) => (
                      <div key={r.id} className="flex items-center gap-2">
                        <Button 
                          variant={selectedRole?.id === r.id ? "default" : "outline"}
                          className={`flex-1 justify-start ${selectedRole?.id === r.id ? 'bg-[#003D82] text-white hover:bg-[#003D82]/90' : ''}`}
                          onClick={() => setSelectedRole(r)}
                        >
                          <Shield className="w-4 h-4 mr-2" />
                          {formatRoleLabel(r.name)}
                        </Button>
                        {!r.is_default && (
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleDeleteRole(r.id)}
                            className="text-red-500 hover:text-red-700 hover:bg-red-50"
                          >
                            <Trash2 className="w-4 h-4" />
                          </Button>
                        )}
                      </div>
                      ))
                    )}
                  </div>
                </div>
                <div className="lg:col-span-3">
                  {selectedRole ? (
                    <>
                      <div className="flex items-center justify-between mb-4">
                        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                          Permissions for <Badge className={getRoleBadgeColor(selectedRole.name)}>{formatRoleLabel(selectedRole.name)}</Badge>
                        </h3>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-6 border">
                        <PermissionsEditor role={selectedRole} onUpdate={fetchData} />
                      </div>
                    </>
                  ) : (
                    <div className="text-center py-12 text-gray-500">
                      <Shield className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                      <p>Select a role to view and edit its permissions</p>
                    </div>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
};

export default RolesPermissionsPage;