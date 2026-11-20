import { apiRequest, apiRequestEnvelope } from "./apiClient";

export type VotingWindowState = "open" | "upcoming" | "closed";

export type VotingPeriodDto = {
  id: string;
  name: string;
  starts_at: string;
  ends_at: string;
  is_active: boolean;
  status: VotingWindowState;
  is_open: boolean;
  time_remaining_seconds: number;
  created_at?: string;
  updated_at?: string;
};

export type VotingStatusDto = {
  status: VotingWindowState;
  is_open: boolean;
  server_time: string;
  period: VotingPeriodDto | null;
};

export type VoteRecordDto = {
  id: string;
  article_id: string;
  voting_period_id: string;
  created_at: string;
  voting_period?: {
    id: string;
    name: string;
  };
  article?: {
    id: string;
    title: string;
    slug: string;
    category?: {
      id: string;
      name: string;
      slug: string;
    } | null;
    author?: {
      id: string;
      name: string;
    } | null;
  } | null;
};

export type CastVoteResultDto = {
  vote: VoteRecordDto;
  article_id: string;
  vote_count: number;
  voting_period_id: string;
};

export type ArticleVoteResultDto = {
  article_id: string;
  slug: string;
  title: string;
  vote_count: number;
  rank: number | null;
  has_voted: boolean;
  voting_status: VotingWindowState;
  updated_at: string;
};

export type LeaderboardEntryDto = {
  rank: number;
  article_id: string;
  title: string;
  slug: string;
  category?: {
    id: string;
    name: string;
    slug: string;
  } | null;
  author?: {
    id: string;
    name: string;
  } | null;
  vote_count: number;
  has_voted: boolean;
  published_at?: string | null;
};

export type LeaderboardResponse = {
  entries: LeaderboardEntryDto[];
  total_votes: number;
  updated_at?: string;
};

export type VotingStatisticsDto = {
  total_votes: number;
  unique_voters: number;
  published_articles_count: number;
  active_period: VotingPeriodDto | null;
  top_articles: LeaderboardEntryDto[];
  by_category: Array<{
    name: string;
    votes: number;
    articles_count: number;
  }>;
  daily_activity: Array<{
    date: string;
    day: string;
    votes: number;
  }>;
};

export type VotingPeriodPayload = {
  name: string;
  starts_at: string;
  ends_at: string;
  is_active?: boolean;
};

export async function getVotingStatus(signal?: AbortSignal): Promise<VotingStatusDto> {
  const response = await apiRequest<{ data: VotingStatusDto }>("/voting/status", { signal });
  return response.data;
}

export async function castArticleVote(articleIdOrSlug: string): Promise<CastVoteResultDto> {
  const response = await apiRequest<{ data: CastVoteResultDto }>(
    `/articles/${encodeURIComponent(articleIdOrSlug)}/votes`,
    { method: "POST" },
  );
  return response.data;
}

export async function getArticleVoteResults(
  articleIdOrSlug: string,
  signal?: AbortSignal,
): Promise<ArticleVoteResultDto> {
  const response = await apiRequest<{ data: ArticleVoteResultDto }>(
    `/articles/${encodeURIComponent(articleIdOrSlug)}/vote-results`,
    { signal },
  );
  return response.data;
}

export async function getMyVotes(signal?: AbortSignal): Promise<VoteRecordDto[]> {
  const response = await apiRequestEnvelope<VoteRecordDto[]>("/me/votes", { signal });
  return response.data;
}

export async function getLeaderboard(signal?: AbortSignal): Promise<LeaderboardResponse> {
  const response = await apiRequest<{
    data: LeaderboardEntryDto[];
    meta?: { total_votes?: number; updated_at?: string };
  }>("/leaderboard", { signal });

  return {
    entries: response.data,
    total_votes: response.meta?.total_votes ?? 0,
    updated_at: response.meta?.updated_at,
  };
}

export async function getAdminVotingPeriods(signal?: AbortSignal): Promise<VotingPeriodDto[]> {
  const response = await apiRequestEnvelope<VotingPeriodDto[]>("/admin/voting-periods", {
    query: { per_page: 100 },
    signal,
  });
  return response.data;
}

export async function createAdminVotingPeriod(
  payload: VotingPeriodPayload,
): Promise<VotingPeriodDto> {
  const response = await apiRequest<{ data: VotingPeriodDto }>("/admin/voting-periods", {
    method: "POST",
    body: payload,
  });
  return response.data;
}

export async function updateAdminVotingPeriod(
  id: string,
  payload: Partial<VotingPeriodPayload>,
): Promise<VotingPeriodDto> {
  const response = await apiRequest<{ data: VotingPeriodDto }>(
    `/admin/voting-periods/${encodeURIComponent(id)}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
  return response.data;
}

export async function activateAdminVotingPeriod(id: string): Promise<VotingPeriodDto> {
  const response = await apiRequest<{ data: VotingPeriodDto }>(
    `/admin/voting-periods/${encodeURIComponent(id)}/activate`,
    { method: "POST" },
  );
  return response.data;
}

export async function getAdminVotingStatistics(
  signal?: AbortSignal,
): Promise<VotingStatisticsDto> {
  const response = await apiRequest<{ data: VotingStatisticsDto }>("/admin/voting/statistics", {
    signal,
  });
  return response.data;
}

