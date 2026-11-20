import { apiRequest } from "./apiClient";

export type MeDto = {
  id: string;
  name: string;
  email: string;
  email_verified: boolean;
  is_active: boolean;
  roles: string[];
  permissions: string[];
  capabilities: {
    is_participant: boolean;
    is_publisher: boolean;
    can_publish_directly: boolean;
  };
  two_factor_enabled: boolean;
  last_login_at?: string | null;
  created_at?: string;
};

export type StaffLoginResult =
  | {
      status: "authenticated";
      user: MeDto;
    }
  | {
      status: "mfa_required" | "mfa_setup_required";
      challenge_token: string;
      expires_in: number;
    };

export type StaffMfaSetupDto = {
  secret: string;
  otpauth_url: string;
  qr_svg: string;
};

export async function getMe(signal?: AbortSignal): Promise<MeDto> {
  const response = await apiRequest<{ data: MeDto }>("/me", { signal });
  return response.data;
}

export async function getGoogleRedirectUrl(): Promise<{
  authorization_url: string;
  expires_in: number;
}> {
  const response = await apiRequest<{
    data: { authorization_url: string; expires_in: number };
  }>("/auth/google/redirect");
  return response.data;
}

export async function staffLogin(email: string, password: string): Promise<StaffLoginResult> {
  const response = await apiRequest<{ data: StaffLoginResult }>("/auth/staff/login", {
    method: "POST",
    body: { email, password },
  });
  return response.data;
}

export async function verifyStaffMfa(
  token: string,
  code: string,
): Promise<{ status: "authenticated"; user: MeDto }> {
  const response = await apiRequest<{ data: { status: "authenticated"; user: MeDto } }>(
    "/auth/staff/mfa/verify",
    {
      method: "POST",
      body: { token, code },
    },
  );
  return response.data;
}

export async function beginStaffMfaSetup(token: string): Promise<StaffMfaSetupDto> {
  const response = await apiRequest<{ data: StaffMfaSetupDto }>("/auth/staff/mfa/setup", {
    method: "POST",
    body: { token },
  });
  return response.data;
}

export async function confirmStaffMfaSetup(
  token: string,
  code: string,
): Promise<{ status: "authenticated"; recovery_codes: string[]; user: MeDto }> {
  const response = await apiRequest<{
    data: { status: "authenticated"; recovery_codes: string[]; user: MeDto };
  }>("/auth/staff/mfa/setup/confirm", {
    method: "POST",
    body: { token, code },
  });
  return response.data;
}

export async function acceptStaffInvitation(payload: {
  token: string;
  name: string;
  password: string;
  password_confirmation: string;
}): Promise<{ user: MeDto }> {
  const response = await apiRequest<{ data: { user: MeDto } }>(
    "/auth/staff/invitations/accept",
    {
      method: "POST",
      body: payload,
    },
  );
  return response.data;
}

export async function forgotStaffPassword(email: string): Promise<void> {
  await apiRequest<{ message?: string }>("/auth/staff/forgot-password", {
    method: "POST",
    body: { email },
  });
}

export async function logoutSession(): Promise<void> {
  await apiRequest<{ message?: string }>("/auth/logout", { method: "POST" });
}
