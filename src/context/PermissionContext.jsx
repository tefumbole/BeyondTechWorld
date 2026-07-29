import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import { getUserPermissions } from '@/services/rolePermissionService';

const PermissionContext = createContext({});

export const usePermission = () => useContext(PermissionContext);

const FULL_ACCESS_ROLES = new Set(['admin', 'administrator', 'super_admin', 'director', 'manager']);

function normalizeRole(role) {
  return String(role || '').trim().toLowerCase().replace(/\s+/g, '_');
}

export const PermissionProvider = ({ children }) => {
  const { user, role, loading: authLoading } = useAuth();
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;

    const loadPermissions = async () => {
      if (authLoading) return;

      if (!user?.id) {
        if (mounted) {
          setPermissions([]);
          setLoading(false);
        }
        return;
      }

      setLoading(true);
      try {
        const perms = await getUserPermissions(user.id);
        if (mounted) setPermissions(Array.isArray(perms) ? perms : []);
      } catch (error) {
        console.error('PermissionContext Error:', error);
        if (mounted) setPermissions([]);
      } finally {
        if (mounted) setLoading(false);
      }
    };

    loadPermissions();

    return () => { mounted = false; };
  }, [user?.id, role, authLoading]);

  const hasPermission = useCallback((permissionKey) => {
    if (!user) return false;

    const normalizedRole = normalizeRole(role);
    if (FULL_ACCESS_ROLES.has(normalizedRole)) {
      return true;
    }

    return Array.isArray(permissions) && permissions.includes(permissionKey);
  }, [user, role, permissions]);

  const hasStaffAccess = FULL_ACCESS_ROLES.has(normalizeRole(role)) || permissions.length > 0;

  return (
    <PermissionContext.Provider value={{
      permissions,
      loading: loading || authLoading,
      hasPermission,
      hasStaffAccess,
    }}>
      {children}
    </PermissionContext.Provider>
  );
};
