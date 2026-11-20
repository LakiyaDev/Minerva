import { apiRequest, apiRequestEnvelope, type ApiEnvelope } from "./apiClient";

export type ArticleStatus =
  | "draft"
  | "pending_review"
  | "approved"
  | "rejected"
  | "published"
  | "unpublished";

export type ArticleCategoryDto = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  articles_count?: number;
};

export type ArticleAuthorDto = {
  id: string;
  name: string;
};

export type ArticleAttachmentDto = {
  id: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  kind: string;
  download_url?: string;
  created_at?: string;
};

export type ArticleStatusHistoryDto = {
  from_status: ArticleStatus | null;
  to_status: ArticleStatus;
  reason?: string | null;
  acted_by?: string | null;
  created_at?: string;
};

export type ArticleSummaryDto = {
  id: string;
  title: string;
  slug: string;
  abstract: string;
  category?: ArticleCategoryDto;
  author?: ArticleAuthorDto;
  status: ArticleStatus;
  status_label?: string;
  is_featured: boolean;
  votes_count: number;
  published_at?: string | null;
  created_at?: string;
};

export type ArticleDetailDto = ArticleSummaryDto & {
  body: string;
  attachments?: ArticleAttachmentDto[];
  status_history?: ArticleStatusHistoryDto[];
};

export type ArticleDraftPayload = {
  title: string;
  abstract: string;
  body: string;
  category_id: string;
};

export type ArticleUpdatePayload = Partial<ArticleDraftPayload>;

export type ArticleListParams = {
  search?: string;
  category?: string;
  sort?: "newest" | "popular";
  page?: number;
  per_page?: number;
};

export type AdminArticleListParams = {
  status?: ArticleStatus | "";
  search?: string;
  category?: string;
  page?: number;
  per_page?: number;
};

export function getArticles(
  params: ArticleListParams = {},
  signal?: AbortSignal,
): Promise<ApiEnvelope<ArticleSummaryDto[]>> {
  return apiRequestEnvelope<ArticleSummaryDto[]>("/articles", {
    query: {
      search: params.search,
      category: params.category,
      sort: params.sort,
      page: params.page,
      per_page: params.per_page,
    },
    signal,
  });
}

export async function getArticle(
  slugOrId: string,
  signal?: AbortSignal,
): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/articles/${encodeURIComponent(slugOrId)}`,
    { signal },
  );
  return response.data;
}

export async function getCategories(signal?: AbortSignal): Promise<ArticleCategoryDto[]> {
  const response = await apiRequest<{ data: ArticleCategoryDto[] }>("/article-categories", {
    signal,
  });
  return response.data;
}

export function getPublisherArticles(
  params: { status?: ArticleStatus | ""; page?: number; per_page?: number } = {},
): Promise<ApiEnvelope<ArticleSummaryDto[]>> {
  return apiRequestEnvelope<ArticleSummaryDto[]>("/publisher/articles", {
    query: { status: params.status, page: params.page, per_page: params.per_page },
  });
}

export async function getPublisherArticle(
  id: string,
  signal?: AbortSignal,
): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/publisher/articles/${encodeURIComponent(id)}`,
    { signal },
  );
  return response.data;
}

export async function createArticleDraft(payload: ArticleDraftPayload): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>("/publisher/articles", {
    method: "POST",
    body: payload,
  });
  return response.data;
}

export async function updateArticleDraft(
  id: string,
  payload: ArticleUpdatePayload,
): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/publisher/articles/${encodeURIComponent(id)}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
  return response.data;
}

export async function submitArticleForReview(id: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/publisher/articles/${encodeURIComponent(id)}/submit`,
    { method: "POST" },
  );
  return response.data;
}

export async function publishArticle(id: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/publisher/articles/${encodeURIComponent(id)}/publish`,
    { method: "POST" },
  );
  return response.data;
}

export function getAdminArticles(
  params: AdminArticleListParams = {},
): Promise<ApiEnvelope<ArticleSummaryDto[]>> {
  return apiRequestEnvelope<ArticleSummaryDto[]>("/admin/articles", {
    query: {
      status: params.status,
      search: params.search,
      category: params.category,
      page: params.page,
      per_page: params.per_page,
    },
  });
}

export function getReviewQueue(): Promise<ApiEnvelope<ArticleSummaryDto[]>> {
  return apiRequestEnvelope<ArticleSummaryDto[]>("/admin/articles/review-queue");
}

export async function approveArticle(id: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/admin/articles/${encodeURIComponent(id)}/approve`,
    { method: "POST" },
  );
  return response.data;
}

export async function rejectArticle(id: string, reason: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/admin/articles/${encodeURIComponent(id)}/reject`,
    {
      method: "POST",
      body: { reason },
    },
  );
  return response.data;
}

export async function adminPublishArticle(id: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/admin/articles/${encodeURIComponent(id)}/publish`,
    { method: "POST" },
  );
  return response.data;
}

export async function unpublishArticle(id: string): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/admin/articles/${encodeURIComponent(id)}/unpublish`,
    { method: "POST" },
  );
  return response.data;
}

export async function toggleFeatureArticle(
  id: string,
  is_featured: boolean,
): Promise<ArticleDetailDto> {
  const response = await apiRequest<{ data: ArticleDetailDto }>(
    `/admin/articles/${encodeURIComponent(id)}/feature`,
    {
      method: "POST",
      body: { is_featured },
    },
  );
  return response.data;
}

export async function uploadAttachment(
  articleId: string,
  file: File,
  kind?: string,
): Promise<ArticleAttachmentDto> {
  const formData = new FormData();
  formData.append("file", file);
  if (kind) {
    formData.append("kind", kind);
  }

  const response = await apiRequest<{ data: ArticleAttachmentDto }>(
    `/publisher/articles/${encodeURIComponent(articleId)}/attachments`,
    {
      method: "POST",
      body: formData,
    },
  );

  return response.data;
}

export async function deleteAttachment(articleId: string, attachmentId: string): Promise<void> {
  await apiRequest<void>(
    `/publisher/articles/${encodeURIComponent(articleId)}/attachments/${encodeURIComponent(attachmentId)}`,
    {
      method: "DELETE",
    },
  );
}

export function getAttachmentDownloadUrl(articleId: string, attachmentId: string): string {
  return `/api/v1/articles/${encodeURIComponent(articleId)}/attachments/${encodeURIComponent(attachmentId)}/download`;
}

export function formatAttachmentSize(bytes: number): string {
  if (bytes >= 1024 * 1024) {
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }
  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
