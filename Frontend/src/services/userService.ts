import { apiRequest, apiRequestEnvelope, type ApiEnvelope } from "./apiClient";

export type AdminUserDto = {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  email_verified: boolean;
  roles: string[];
  direct_permissions: Array<{ name: string; expires_at: string | null }>;
  publisher_permission: {
    can_publish_directly: boolean;
    granted_at: string | null;
  } | null;
  last_login_at: string | null;
  created_at: string | null;
};

export type StaffInvitationDto = {
  id: string;
  email: string;
  role: string;
  expires_at: string | null;
  accepted_at: string | null;
  invited_by: string | null;
  created_at: string | null;
};

export type AdminUserQuery = {
  q?: string;
  role?: string;
  is_active?: string;
  page?: number;
  per_page?: number;
};

export function getAdminUsers(
  params: AdminUserQuery = {},
  signal?: AbortSignal,
): Promise<ApiEnvelope<AdminUserDto[]>> {
  return apiRequestEnvelope<AdminUserDto[]>("/admin/users", {
    query: {
      q: params.q || undefined,
      role: params.role || undefined,
      is_active: params.is_active === "" ? undefined : params.is_active,
      page: params.page,
      per_page: params.per_page ?? 20,
    },
    signal,
  });
}

export async function updateAdminUser(
  userId: string,
  payload: { name?: string; is_active?: boolean },
): Promise<AdminUserDto> {
  const response = await apiRequest<{ data: AdminUserDto }>(
    `/admin/users/${encodeURIComponent(userId)}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
  return response.data;
}

export async function grantPublisherPermission(userId: string): Promise<void> {
  await apiRequest(`/admin/users/${encodeURIComponent(userId)}/publisher-permission`, {
    method: "POST",
    body: { can_publish_directly: true },
  });
}

export async function revokePublisherPermission(userId: string): Promise<void> {
  await apiRequest(`/admin/users/${encodeURIComponent(userId)}/publisher-permission`, {
    method: "DELETE",
  });
}

export function getStaffInvitations(
  signal?: AbortSignal,
): Promise<ApiEnvelope<StaffInvitationDto[]>> {
  return apiRequestEnvelope<StaffInvitationDto[]>("/admin/staff-invitations", { signal });
}

export async function inviteStaffMember(
  email: string,
  role: "publisher" | "administrator",
): Promise<StaffInvitationDto> {
  const response = await apiRequest<{ data: StaffInvitationDto }>("/admin/staff-invitations", {
    method: "POST",
    body: { email, role },
  });
  return response.data;
}
