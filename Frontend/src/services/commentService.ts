import { apiRequest, apiRequestEnvelope, type ApiEnvelope } from "./apiClient";

export type CommentStatus = "visible" | "removed";

export type CommentModerationRecordDto = {
  id: string;
  action: string;
  reason: string;
  actor?: {
    id: string;
    name: string;
  };
  created_at?: string;
};

export type CommentDto = {
  id: string;
  article_id: string;
  body: string;
  status: CommentStatus;
  is_removed: boolean;
  author?: {
    id: string;
    name: string;
  };
  article?: {
    id: string;
    title: string;
    slug: string;
  };
  moderation_records?: CommentModerationRecordDto[];
  removed_at?: string | null;
  created_at?: string;
  updated_at?: string;
};

export function getArticleComments(
  articleId: string,
  page = 1,
  signal?: AbortSignal,
): Promise<ApiEnvelope<CommentDto[]>> {
  return apiRequestEnvelope<CommentDto[]>(
    `/articles/${encodeURIComponent(articleId)}/comments`,
    {
      query: { page },
      signal,
    },
  );
}

export async function postComment(articleId: string, body: string): Promise<CommentDto> {
  const response = await apiRequest<{ data: CommentDto }>(
    `/articles/${encodeURIComponent(articleId)}/comments`,
    {
      method: "POST",
      body: { body },
    },
  );
  return response.data;
}

export async function deleteComment(commentId: string): Promise<void> {
  await apiRequest<{ message?: string }>(`/comments/${encodeURIComponent(commentId)}`, {
    method: "DELETE",
  });
}

export function getMyComments(
  page = 1,
  signal?: AbortSignal,
): Promise<ApiEnvelope<CommentDto[]>> {
  return apiRequestEnvelope<CommentDto[]>("/me/comments", {
    query: { page },
    signal,
  });
}

export function getAdminComments(
  params: { status?: string; page?: number } = {},
  signal?: AbortSignal,
): Promise<ApiEnvelope<CommentDto[]>> {
  return apiRequestEnvelope<CommentDto[]>("/admin/comments", {
    query: { status: params.status, page: params.page },
    signal,
  });
}

export async function moderateRemoveComment(
  commentId: string,
  reason: string,
): Promise<CommentDto> {
  const response = await apiRequest<{ data: CommentDto }>(
    `/admin/comments/${encodeURIComponent(commentId)}/remove`,
    {
      method: "POST",
      body: { reason },
    },
  );
  return response.data;
}
