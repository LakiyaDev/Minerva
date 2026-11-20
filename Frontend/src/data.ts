export type ArticleStatus =
  | "draft"
  | "pending_review"
  | "approved"
  | "rejected"
  | "published"
  | "unpublished";

export type ArticleCategory = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  articles_count?: number;
};

export type ArticleAttachment = {
  id: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  kind: string;
};

export type ArticleStatusHistoryEntry = {
  from_status: ArticleStatus | null;
  to_status: ArticleStatus;
  reason?: string | null;
  acted_by?: string | null;
  created_at?: string;
};

export type Article = {
  id: string;
  /** Public slug used in /articles/:slug URLs. Falls back to the id. */
  slug: string;
  title: string;
  author: string;
  author_id?: string;
  faculty?: string;
  category: string;
  category_id?: string;
  category_slug?: string;
  date: string;
  abstract: string;
  body?: string;
  excerpt: string;
  votes: number;
  status: ArticleStatus;
  is_featured?: boolean;
  published_at?: string | null;
  created_at?: string;
  voting: "open" | "closed";
  image: string;
  attachments?: ArticleAttachment[];
  status_history?: ArticleStatusHistoryEntry[];
};

/**
 * Editorial status vocabulary shared by the publisher and admin screens.
 */
export const articleStatusLabels: Record<ArticleStatus, string> = {
  draft: "Draft",
  pending_review: "Pending Review",
  approved: "Approved",
  rejected: "Rejected",
  published: "Published",
  unpublished: "Unpublished",
};

export const initialArticles: Article[] = [];

export const participants: Array<
  [string, string, string, number, "active" | "inactive"]
> = [];

export const activity: Array<{ day: string; votes: number }> = [];
