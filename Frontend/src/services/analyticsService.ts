import { apiRequest } from "./apiClient";

export type DailyVotePoint = {
  date: string;
  label: string;
  votes: number;
};

export type ArticleAnalyticsDto = {
  article: {
    id: string;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    is_featured: boolean;
    published_at: string | null;
    category: {
      id: string;
      name: string;
      slug: string;
    } | null;
    author: {
      id: string;
      name: string;
    } | null;
  };
  engagement: {
    total_votes: number;
    rank: number | null;
    total_comments: number;
    visible_comments_count: number;
    removed_comments_count: number;
  };
  benchmarks: {
    category_average_votes: number;
    platform_average_votes: number;
  };
  daily_votes: DailyVotePoint[];
};

export type CategoryAnalytics = {
  id: string;
  name: string;
  slug: string;
  articles_count: number;
  published_count: number;
  votes_count: number;
  vote_share_percent: number;
};

export type TopPerformingArticle = {
  id: string;
  title: string;
  slug: string;
  votes_count: number;
  author: {
    id: string;
    name: string;
  } | null;
  category: {
    id: string;
    name: string;
  } | null;
};

export type AdminAnalyticsDto = {
  articles_summary: {
    total: number;
    by_status: Record<string, number>;
    featured_count: number;
  };
  categories: CategoryAnalytics[];
  voting: {
    total_votes: number;
    unique_voters: number;
    active_period: {
      id: string;
      name: string;
      is_active: boolean;
      starts_at: string | null;
      ends_at: string | null;
    } | null;
    daily_trend: DailyVotePoint[];
  };
  community: {
    total_comments: number;
    visible_comments: number;
    removed_comments: number;
    moderation_rate_percent: number;
  };
  top_performing_articles: TopPerformingArticle[];
};

export async function getPublisherArticleAnalytics(
  articleId: string,
  signal?: AbortSignal,
): Promise<ArticleAnalyticsDto> {
  const response = await apiRequest<{ data: ArticleAnalyticsDto }>(
    `/publisher/articles/${encodeURIComponent(articleId)}/analytics`,
    { signal },
  );
  return response.data;
}

export async function getAdminAnalytics(signal?: AbortSignal): Promise<AdminAnalyticsDto> {
  const response = await apiRequest<{ data: AdminAnalyticsDto }>("/admin/analytics", {
    signal,
  });
  return response.data;
}
