import { apiRequest } from "./apiClient";

export type AuditLogActor = {
  id: string;
  name: string;
  email: string;
};

export type AuditLogDto = {
  id: string;
  action: string;
  actor: AuditLogActor | null;
  subject_type: string | null;
  subject_id: string | null;
  metadata: Record<string, unknown>;
  ip_hash: string | null;
  created_at: string;
};

export type AuditLogFilterParams = {
  action?: string;
  actor_id?: string;
  subject_type?: string;
  subject_id?: string;
  search?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
};

export type PaginatedAuditLogs = {
  data: AuditLogDto[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page?: number;
    from?: number;
    to?: number;
  };
};

export async function getAdminAuditLogs(
  params?: AuditLogFilterParams,
  signal?: AbortSignal,
): Promise<PaginatedAuditLogs> {
  const query = new URLSearchParams();
  if (params?.action) query.set("action", params.action);
  if (params?.actor_id) query.set("actor_id", params.actor_id);
  if (params?.subject_type) query.set("subject_type", params.subject_type);
  if (params?.subject_id) query.set("subject_id", params.subject_id);
  if (params?.search) query.set("search", params.search);
  if (params?.date_from) query.set("date_from", params.date_from);
  if (params?.date_to) query.set("date_to", params.date_to);
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));

  const qs = query.toString();
  const endpoint = `/admin/audit-logs${qs ? `?${qs}` : ""}`;

  const response = await apiRequest<{
    data: AuditLogDto[];
    meta: PaginatedAuditLogs["meta"];
  }>(endpoint, { signal });

  return {
    data: response.data,
    meta: response.meta ?? {
      current_page: 1,
      per_page: response.data.length,
      total: response.data.length,
    },
  };
}
