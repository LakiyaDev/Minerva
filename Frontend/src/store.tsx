import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { initialArticles, type Article, type ArticleCategory, type ArticleStatus } from "./data";
import {
  approveArticle as approveArticleRequest,
  adminPublishArticle as adminPublishArticleRequest,
  createArticleDraft,
  getAdminArticles,
  getArticles,
  getCategories,
  getPublisherArticles,
  publishArticle as publishArticleRequest,
  rejectArticle as rejectArticleRequest,
  submitArticleForReview,
  toggleFeatureArticle,
  unpublishArticle as unpublishArticleRequest,
  updateArticleDraft,
  type ArticleDetailDto,
  type ArticleDraftPayload,
  type ArticleSummaryDto,
  type ArticleUpdatePayload,
} from "./services/articleService";
import {
  castArticleVote,
  getMyVotes,
  getVotingStatus,
  type VoteRecordDto,
  type VotingStatusDto,
} from "./services/votingService";
import { getMe, logoutSession, type MeDto } from "./services/authService";
import { ApiError, ApiUnavailableError } from "./services/apiClient";

export type Role = "guest" | "participant" | "publisher" | "admin";

type LoadState = "idle" | "loading" | "ready" | "error";

export type EditorialResult =
  | { ok: true; id?: string }
  | { ok: false; message: string; fieldErrors?: Record<string, string[]> };

export type CastVoteOutcome =
  | { ok: true; voteCount: number }
  | { ok: false; code: string; message: string };

type Store = {
  role: Role;
  setRole: (role: Role) => void;
  user: MeDto | null;
  authChecked: boolean;
  isAdmin: boolean;
  isPublisher: boolean;
  canPublishDirectly: boolean;
  hasPermission: (permission: string) => boolean;
  refreshMe: () => Promise<MeDto | null>;
  setAuthenticatedUser: (user: MeDto | null) => void;
  logout: () => Promise<void>;
  articles: Article[];
  categories: ArticleCategory[];
  articlesState: LoadState;
  articlesError: string | null;
  refreshArticles: () => Promise<void>;
  refreshCategories: () => Promise<void>;
  votingStatus: VotingStatusDto | null;
  voteRecords: VoteRecordDto[];
  refreshVotingStatus: () => Promise<void>;
  refreshMyVotes: () => Promise<void>;
  voted: string[];
  authOpen: boolean;
  setAuthOpen: (open: boolean) => void;
  pendingVote: string | null;
  requestVote: (id: string) => void;
  castVote: (id: string) => Promise<CastVoteOutcome>;
  toggleVoting: (id: string) => void;
  togglePublished: (id: string) => void;
  deleteArticle: (id: string) => void;
  addArticle: (article: Article) => void;
  unread: number;
  markRead: () => void;
  // Editorial workflow actions (backend-authoritative).
  createDraft: (payload: ArticleDraftPayload) => Promise<EditorialResult>;
  updateDraft: (id: string, payload: ArticleUpdatePayload) => Promise<EditorialResult>;
  submitForReview: (id: string) => Promise<EditorialResult>;
  publishOwn: (id: string) => Promise<EditorialResult>;
  approve: (id: string) => Promise<EditorialResult>;
  reject: (id: string, reason: string) => Promise<EditorialResult>;
  publishAny: (id: string) => Promise<EditorialResult>;
  unpublish: (id: string) => Promise<EditorialResult>;
  toggleFeatured: (id: string, isFeatured: boolean) => Promise<EditorialResult>;
  loadPublisherArticles: () => Promise<Article[]>;
  loadAdminArticles: (status?: ArticleStatus | "") => Promise<Article[]>;
};

export function deriveRole(user: MeDto | null): Role {
  if (!user) return "guest";

  if (
    user.roles.includes("administrator") ||
    user.permissions.some((permission) =>
      ["article.review", "user.manage", "settings.manage"].includes(permission),
    )
  ) {
    return "admin";
  }

  if (
    user.capabilities.is_publisher ||
    user.roles.includes("publisher") ||
    user.permissions.includes("article.create")
  ) {
    return "publisher";
  }

  return "participant";
}

const AppContext = createContext<Store | null>(null);

function formatDate(value?: string | null): string {
  if (!value) return "—";

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return "—";

  return date.toLocaleDateString("en-US", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

export function mapArticle(dto: ArticleSummaryDto | ArticleDetailDto): Article {
  const detail = dto as ArticleDetailDto;

  return {
    id: dto.id,
    slug: dto.slug || dto.id,
    title: dto.title,
    author: dto.author?.name || "Unknown author",
    author_id: dto.author?.id,
    faculty: "University of Sri Jayewardenepura",
    category: dto.category?.name || "Uncategorised",
    category_id: dto.category?.id,
    category_slug: dto.category?.slug,
    date: formatDate(dto.published_at || dto.created_at),
    abstract: dto.abstract || "",
    body: detail.body,
    excerpt: dto.abstract || "",
    votes: dto.votes_count ?? 0,
    status: dto.status,
    is_featured: dto.is_featured,
    published_at: dto.published_at,
    created_at: dto.created_at,
    voting: "closed",
    image: "",
    attachments: detail.attachments,
    status_history: detail.status_history,
  };
}

function errorMessage(error: unknown): string {
  if (error instanceof ApiUnavailableError) {
    return "The MINERVA API is unreachable. Showing locally cached content.";
  }

  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Something went wrong.";
}

export function AppProvider({ children }: { children: ReactNode }) {
  const [role, setRoleState] = useState<Role>(() => {
    localStorage.removeItem("minerva-role");
    return "guest";
  });
  const [user, setUser] = useState<MeDto | null>(null);
  const [authChecked, setAuthChecked] = useState(false);
  const [articles, setArticles] = useState(initialArticles);
  const [categories, setCategories] = useState<ArticleCategory[]>([]);
  const [articlesState, setArticlesState] = useState<LoadState>("idle");
  const [articlesError, setArticlesError] = useState<string | null>(null);
  const [votingStatus, setVotingStatus] = useState<VotingStatusDto | null>(null);
  const votingOpenRef = useRef(false);
  const [voteRecords, setVoteRecords] = useState<VoteRecordDto[]>([]);
  const [voted, setVoted] = useState<string[]>([]);
  const [authOpen, setAuthOpen] = useState(false);
  const [pendingVote, setPendingVote] = useState<string | null>(null);
  const [unread, setUnread] = useState(0);

  const setRole = useCallback(
    (next: Role) => {
      setRoleState(next);
      if (next === "guest") {
        setUser(null);
        localStorage.removeItem("minerva-role");
      } else {
        localStorage.setItem("minerva-role", next);
      }
      if (next !== "guest" && pendingVote) setAuthOpen(false);
    },
    [pendingVote],
  );

  const setAuthenticatedUser = useCallback(
    (nextUser: MeDto | null) => {
      setUser(nextUser);
      const nextRole = deriveRole(nextUser);
      setRoleState(nextRole);
      if (nextRole === "guest") {
        localStorage.removeItem("minerva-role");
      } else {
        localStorage.setItem("minerva-role", nextRole);
        if (pendingVote) setAuthOpen(false);
      }
      setAuthChecked(true);
    },
    [pendingVote],
  );

  const refreshMe = useCallback(async (): Promise<MeDto | null> => {
    try {
      const me = await getMe();
      setAuthenticatedUser(me);
      return me;
    } catch {
      setAuthenticatedUser(null);
      return null;
    } finally {
      setAuthChecked(true);
    }
  }, [setAuthenticatedUser]);

  const logout = useCallback(async () => {
    try {
      await logoutSession();
    } catch {
      // Clear local session state even if session already expired.
    }
    setUser(null);
    setRoleState("guest");
    setVoted([]);
    setVoteRecords([]);
    localStorage.removeItem("minerva-role");
  }, []);

  const hasPermission = useCallback(
    (permission: string) => user?.permissions.includes(permission) ?? false,
    [user],
  );

  const isAdmin =
    role === "admin" ||
    (user?.roles.includes("administrator") ?? false) ||
    hasPermission("article.review") ||
    hasPermission("user.manage") ||
    hasPermission("settings.manage");

  const isPublisher =
    role === "publisher" ||
    Boolean(user?.capabilities.is_publisher) ||
    (user?.roles.includes("publisher") ?? false) ||
    hasPermission("article.create");

  const canPublishDirectly =
    Boolean(user?.capabilities.can_publish_directly) || hasPermission("article.publish_own");

  const requestVote = useCallback(
    (id: string) => {
      if (role === "guest") {
        setPendingVote(id);
        setAuthOpen(true);
      }
    },
    [role],
  );

  const refreshVotingStatus = useCallback(async () => {
    try {
      const status = await getVotingStatus();
      votingOpenRef.current = status.is_open;
      setVotingStatus(status);
      const nextVoting: "open" | "closed" = status.is_open ? "open" : "closed";
      setArticles((current) =>
        current.map((item) => (item.voting === nextVoting ? item : { ...item, voting: nextVoting })),
      );
    } catch {
      // Voting status falls back to the last known state when unreachable.
    }
  }, []);

  const refreshMyVotes = useCallback(async () => {
    try {
      const records = await getMyVotes();
      setVoteRecords(records);
      setVoted(records.map((entry) => entry.article_id));
    } catch {
      // Unauthenticated or unreachable sessions keep an empty vote cache.
    }
  }, []);

  const castVote = useCallback(
    async (id: string): Promise<CastVoteOutcome> => {
      try {
        const result = await castArticleVote(id);
        setVoted((current) => (current.includes(id) ? current : [...current, id]));
        setVoteRecords((current) => [
          result.vote,
          ...current.filter((entry) => entry.article_id !== id),
        ]);
        setArticles((current) =>
          current.map((item) =>
            item.id === id ? { ...item, votes: result.vote_count } : item,
          ),
        );
        setPendingVote(null);
        return { ok: true, voteCount: result.vote_count };
      } catch (error) {
        if (error instanceof ApiError) {
          if (error.code === "vote_already_cast" || error.status === 409) {
            setVoted((current) => (current.includes(id) ? current : [...current, id]));
            return {
              ok: false,
              code: error.code || "vote_already_cast",
              message: error.message,
            };
          }
          if (error.code === "voting_period_inactive" || error.status === 422) {
            void refreshVotingStatus();
            return {
              ok: false,
              code: error.code || "voting_period_inactive",
              message: error.message,
            };
          }
          if (error.status === 401) {
            requestVote(id);
            return { ok: false, code: "unauthenticated", message: error.message };
          }
          return { ok: false, code: error.code, message: error.message };
        }

        return { ok: false, code: "network_error", message: errorMessage(error) };
      }
    },
    [refreshVotingStatus, requestVote],
  );

  const toggleVoting = (id: string) =>
    setArticles((current) =>
      current.map((a) =>
        a.id === id ? { ...a, voting: a.voting === "open" ? "closed" : "open" } : a,
      ),
    );
  const togglePublished = (id: string) =>
    setArticles((current) =>
      current.map((a) =>
        a.id === id ? { ...a, status: a.status === "published" ? "draft" : "published" } : a,
      ),
    );
  const deleteArticle = (id: string) =>
    setArticles((current) => current.filter((a) => a.id !== id));
  const addArticle = (article: Article) => setArticles((current) => [article, ...current]);

  const refreshArticles = useCallback(async () => {
    setArticlesState("loading");
    setArticlesError(null);

    try {
      const response = await getArticles({ per_page: 100, sort: "newest" });
      const voting: "open" | "closed" = votingOpenRef.current ? "open" : "closed";
      setArticles(response.data.map((dto) => ({ ...mapArticle(dto), voting })));
      setArticlesState("ready");
    } catch (error) {
      setArticlesError(errorMessage(error));
      setArticlesState("error");
    }
  }, []);

  const refreshCategories = useCallback(async () => {
    try {
      setCategories(await getCategories());
    } catch {
      // Categories are optional decoration; a failure keeps the last list.
    }
  }, []);

  useEffect(() => {
    void refreshMe();
    void refreshArticles();
    void refreshCategories();
    void refreshVotingStatus();
  }, [refreshMe, refreshArticles, refreshCategories, refreshVotingStatus]);

  useEffect(() => {
    if (role === "guest") {
      setVoted([]);
      setVoteRecords([]);
      return;
    }

    void refreshMyVotes();
  }, [role, refreshMyVotes]);

  /** Applies a workflow response to the locally cached list. */
  const applyArticle = useCallback((dto: ArticleDetailDto) => {
    const mapped: Article = {
      ...mapArticle(dto),
      voting: votingOpenRef.current ? "open" : "closed",
    };
    setArticles((current) => {
      const exists = current.some((article) => article.id === mapped.id);
      return exists
        ? current.map((article) => (article.id === mapped.id ? mapped : article))
        : [mapped, ...current];
    });
  }, []);

  const runAction = useCallback(
    async (action: () => Promise<ArticleDetailDto>): Promise<EditorialResult> => {
      try {
        applyArticle(await action());
        return { ok: true };
      } catch (error) {
        if (error instanceof ApiError) {
          return { ok: false, message: error.message, fieldErrors: error.errors };
        }
        return { ok: false, message: errorMessage(error) };
      }
    },
    [applyArticle],
  );

  const createDraft = useCallback(
    async (payload: ArticleDraftPayload): Promise<EditorialResult> => {
      try {
        const created = await createArticleDraft(payload);
        applyArticle(created);
        return { ok: true, id: created.id };
      } catch (error) {
        if (error instanceof ApiError) {
          return { ok: false, message: error.message, fieldErrors: error.errors };
        }
        return { ok: false, message: errorMessage(error) };
      }
    },
    [applyArticle],
  );

  const updateDraft = useCallback(
    async (id: string, payload: ArticleUpdatePayload): Promise<EditorialResult> =>
      runAction(() => updateArticleDraft(id, payload)),
    [runAction],
  );

  const submitForReview = useCallback(
    async (id: string): Promise<EditorialResult> =>
      runAction(() => submitArticleForReview(id)),
    [runAction],
  );

  const publishOwn = useCallback(
    async (id: string): Promise<EditorialResult> => runAction(() => publishArticleRequest(id)),
    [runAction],
  );

  const approve = useCallback(
    async (id: string): Promise<EditorialResult> => runAction(() => approveArticleRequest(id)),
    [runAction],
  );

  const reject = useCallback(
    async (id: string, reason: string): Promise<EditorialResult> =>
      runAction(() => rejectArticleRequest(id, reason)),
    [runAction],
  );

  const publishAny = useCallback(
    async (id: string): Promise<EditorialResult> =>
      runAction(() => adminPublishArticleRequest(id)),
    [runAction],
  );

  const unpublish = useCallback(
    async (id: string): Promise<EditorialResult> => runAction(() => unpublishArticleRequest(id)),
    [runAction],
  );

  const toggleFeatured = useCallback(
    async (id: string, isFeatured: boolean): Promise<EditorialResult> =>
      runAction(() => toggleFeatureArticle(id, isFeatured)),
    [runAction],
  );

  const loadPublisherArticles = useCallback(async (): Promise<Article[]> => {
    const response = await getPublisherArticles({ per_page: 100 });
    return response.data.map(mapArticle);
  }, []);

  const loadAdminArticles = useCallback(
    async (status?: ArticleStatus | ""): Promise<Article[]> => {
      const response = await getAdminArticles({ status, per_page: 100 });
      return response.data.map(mapArticle);
    },
    [],
  );

  const value = useMemo(
    () => ({
      role,
      setRole,
      user,
      authChecked,
      isAdmin,
      isPublisher,
      canPublishDirectly,
      hasPermission,
      refreshMe,
      setAuthenticatedUser,
      logout,
      articles,
      categories,
      articlesState,
      articlesError,
      refreshArticles,
      refreshCategories,
      votingStatus,
      voteRecords,
      refreshVotingStatus,
      refreshMyVotes,
      voted,
      authOpen,
      setAuthOpen,
      pendingVote,
      requestVote,
      castVote,
      toggleVoting,
      togglePublished,
      deleteArticle,
      addArticle,
      unread,
      markRead: () => setUnread(0),
      createDraft,
      updateDraft,
      submitForReview,
      publishOwn,
      approve,
      reject,
      publishAny,
      unpublish,
      toggleFeatured,
      loadPublisherArticles,
      loadAdminArticles,
    }),
    [
      role,
      setRole,
      user,
      authChecked,
      isAdmin,
      isPublisher,
      canPublishDirectly,
      hasPermission,
      refreshMe,
      setAuthenticatedUser,
      logout,
      articles,
      categories,
      articlesState,
      articlesError,
      refreshArticles,
      refreshCategories,
      votingStatus,
      voteRecords,
      refreshVotingStatus,
      refreshMyVotes,
      voted,
      authOpen,
      pendingVote,
      requestVote,
      castVote,
      unread,
      createDraft,
      updateDraft,
      submitForReview,
      publishOwn,
      approve,
      reject,
      publishAny,
      unpublish,
      toggleFeatured,
      loadPublisherArticles,
      loadAdminArticles,
    ],
  );
  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) throw new Error("useApp must be used inside AppProvider");
  return context;
}
