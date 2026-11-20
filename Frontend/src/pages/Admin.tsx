import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  ArrowUpRight,
  Check,
  Edit3,
  Eye,
  EyeOff,
  FileText,
  MessageSquare,
  Plus,
  Search,
  Star,
  ToggleLeft,
  Trash2,
  UsersRound,
  Vote,
  X,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Link, useLocation, useNavigate } from "react-router";
import { activity, articleStatusLabels, participants, type Article, type ArticleStatus } from "../data";
import { useApp, type EditorialResult } from "../store";
import { ApiError, ApiUnavailableError } from "../services/apiClient";
import {
  activateAdminVotingPeriod,
  createAdminVotingPeriod,
  getAdminVotingPeriods,
  getAdminVotingStatistics,
  updateAdminVotingPeriod,
  type VotingPeriodDto,
  type VotingStatisticsDto,
} from "../services/votingService";
import {
  getAdminComments,
  moderateRemoveComment,
  type CommentDto,
  type CommentStatus,
} from "../services/commentService";
import {
  getAdminAnalytics,
  type AdminAnalyticsDto,
} from "../services/analyticsService";
import {
  getAdminAuditLogs,
  type AuditLogDto,
  type PaginatedAuditLogs,
} from "../services/auditService";

function toDateTimeLocal(iso?: string): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDateTime(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString("en-US", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function AdminOverview() {
  const { articles, votingStatus } = useApp();
  const [statistics, setStatistics] = useState<VotingStatisticsDto | null>(null);
  const [analytics, setAnalytics] = useState<AdminAnalyticsDto | null>(null);
  const [recentLogs, setRecentLogs] = useState<AuditLogDto[]>([]);

  useEffect(() => {
    const controller = new AbortController();
    getAdminVotingStatistics(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setStatistics(data);
      })
      .catch(() => {
        // Keep fallback summary cards if API is unreachable or unauthorized.
      });

    getAdminAnalytics(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setAnalytics(data);
      })
      .catch(() => {
        // Keep fallback if analytics API is unavailable.
      });

    getAdminAuditLogs({ per_page: 5 }, controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setRecentLogs(data.data);
      })
      .catch(() => {
        // Keep empty logs if unauthorized or unreachable.
      });

    return () => controller.abort();
  }, []);

  const published = articles.filter((a) => a.status === "published");
  const votes =
    analytics?.voting.total_votes ??
    statistics?.total_votes ??
    articles.reduce((s, a) => s + a.votes, 0);

  const chart = analytics?.top_performing_articles?.length
    ? analytics.top_performing_articles.slice(0, 6).map((a) => ({
        name: a.title.slice(0, 16) + (a.title.length > 16 ? "…" : ""),
        votes: a.votes_count,
      }))
    : statistics
      ? statistics.top_articles.slice(0, 6).map((a) => ({
          name: a.title.slice(0, 16) + (a.title.length > 16 ? "…" : ""),
          votes: a.vote_count,
        }))
      : [...published]
          .sort((a, b) => b.votes - a.votes)
          .slice(0, 6)
          .map((a) => ({ name: a.title.slice(0, 16) + "…", votes: a.votes }));

  const windowIsOpen =
    analytics?.voting.active_period?.is_active ??
    statistics?.active_period?.is_open ??
    votingStatus?.is_open ??
    false;

  const trendData = analytics?.voting.daily_trend?.length
    ? analytics.voting.daily_trend.map((point) => ({
        day: point.label,
        votes: point.votes,
      }))
    : (statistics?.daily_activity ?? activity);

  const stats: Array<[LucideIcon, string | number, string, string]> = [
    [
      UsersRound,
      analytics?.voting.unique_voters ?? statistics?.unique_voters ?? 0,
      "unique voters",
      "Registered ballots",
    ],
    [
      FileText,
      analytics?.articles_summary.by_status.published ??
        statistics?.published_articles_count ??
        published.length,
      "published articles",
      analytics?.articles_summary.total
        ? `${analytics.articles_summary.total} total in repository`
        : "Competition entries",
    ],
    [Vote, votes.toLocaleString(), "total votes", "Final recorded votes"],
    [
      ToggleLeft,
      windowIsOpen ? "Open" : "Closed",
      "ballot window",
      analytics?.voting.active_period?.name ??
        statistics?.active_period?.name ??
        votingStatus?.period?.name ??
        "No active period",
    ],
  ];
  return (
    <div className="admin-page">
      <PageHead
        eyebrow="editorial overview."
        title="Good morning, editor."
        action={
          <Link className="btn" to="/admin/create">
            <Plus /> new article
          </Link>
        }
      />
      <div className="admin-stats">
        {stats.map(([Icon, value, label, note]) => (
          <div key={label}>
            <Icon />
            <span>{label}</span>
            <strong>{value}</strong>
            <small>{note}</small>
          </div>
        ))}
      </div>

      {analytics && (
        <div
          className="admin-analytics-summary"
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
            gap: "1rem",
            marginBottom: "1.5rem",
            padding: "1rem 1.25rem",
            background: "#faf8f5",
            border: "1px solid #e4dcc9",
            borderRadius: "6px",
          }}
        >
          <div>
            <span
              style={{
                fontSize: "0.75rem",
                textTransform: "uppercase",
                letterSpacing: "0.08em",
                color: "#6b705c",
                display: "block",
                marginBottom: "0.25rem",
              }}
            >
              Pending Review
            </span>
            <strong style={{ fontSize: "1.25rem", color: "#2e2a21" }}>
              {analytics.articles_summary.by_status.pending_review ?? 0}
            </strong>
          </div>
          <div>
            <span
              style={{
                fontSize: "0.75rem",
                textTransform: "uppercase",
                letterSpacing: "0.08em",
                color: "#6b705c",
                display: "block",
                marginBottom: "0.25rem",
              }}
            >
              Drafts / In Progress
            </span>
            <strong style={{ fontSize: "1.25rem", color: "#2e2a21" }}>
              {analytics.articles_summary.by_status.draft ?? 0}
            </strong>
          </div>
          <div>
            <span
              style={{
                fontSize: "0.75rem",
                textTransform: "uppercase",
                letterSpacing: "0.08em",
                color: "#6b705c",
                display: "block",
                marginBottom: "0.25rem",
              }}
            >
              Community Comments
            </span>
            <strong style={{ fontSize: "1.25rem", color: "#2e2a21" }}>
              {analytics.community.total_comments}
            </strong>
          </div>
          <div>
            <span
              style={{
                fontSize: "0.75rem",
                textTransform: "uppercase",
                letterSpacing: "0.08em",
                color: "#6b705c",
                display: "block",
                marginBottom: "0.25rem",
              }}
            >
              Moderation Rate
            </span>
            <strong style={{ fontSize: "1.25rem", color: "#2e2a21" }}>
              {analytics.community.moderation_rate_percent}%
            </strong>
          </div>
        </div>
      )}

      <div className="charts-grid">
        <section>
          <header>
            <div>
              <span className="eyebrow">voting activity.</span>
              <h2>Votes over time</h2>
            </div>
            <select>
              <option>Last 14 days</option>
            </select>
          </header>
          <ResponsiveContainer width="100%" height={280}>
            <AreaChart data={trendData}>
              <defs>
                <linearGradient id="bronze" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#8A6D3B" stopOpacity=".35" />
                  <stop offset="100%" stopColor="#8A6D3B" stopOpacity="0" />
                </linearGradient>
              </defs>
              <CartesianGrid stroke="#e4dcc9" vertical={false} />
              <XAxis dataKey="day" axisLine={false} tickLine={false} />
              <YAxis axisLine={false} tickLine={false} />
              <Tooltip />
              <Area
                type="monotone"
                dataKey="votes"
                stroke="#8A6D3B"
                strokeWidth={2}
                fill="url(#bronze)"
              />
            </AreaChart>
          </ResponsiveContainer>
        </section>
        <section>
          <header>
            <div>
              <span className="eyebrow">leading essays.</span>
              <h2>Votes by article</h2>
            </div>
          </header>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={chart} layout="vertical">
              <XAxis type="number" hide />
              <YAxis dataKey="name" type="category" axisLine={false} tickLine={false} width={115} />
              <Tooltip />
              <Bar dataKey="votes" fill="#8A6D3B" radius={[0, 4, 4, 0]} barSize={18} />
            </BarChart>
          </ResponsiveContainer>
        </section>
      </div>
      <section className="admin-activity">
        <header style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div>
            <span className="eyebrow">recent activity.</span>
            <h2>What changed today</h2>
          </div>
          <Link
            to="/admin/audit-logs"
            className="btn outline"
            style={{ fontSize: "0.82rem", padding: "0.35rem 0.75rem" }}
          >
            view audit trail →
          </Link>
        </header>
        {recentLogs.length > 0 ? (
          <div style={{ display: "grid", gap: "0.6rem", marginTop: "1rem" }}>
            {recentLogs.map((log) => (
              <div
                key={log.id}
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  padding: "0.75rem 1rem",
                  background: "#fff",
                  border: "1px solid #e4dcc9",
                  borderRadius: "6px",
                  fontSize: "0.88rem",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: "0.75rem", flexWrap: "wrap" }}>
                  <code
                    style={{
                      fontSize: "0.78rem",
                      background: "rgba(138,109,59,0.1)",
                      color: "#8a6d3b",
                      padding: "2px 6px",
                      borderRadius: "4px",
                    }}
                  >
                    {log.action}
                  </code>
                  <span>
                    {log.actor?.name ? <strong>{log.actor.name}</strong> : <span style={{ opacity: 0.6 }}>System</span>}
                    {log.subject_type && (
                      <span style={{ opacity: 0.7, marginLeft: "0.4rem" }}>
                        · {log.subject_type.split("\\").pop()} {log.subject_id ? `#${log.subject_id.slice(0, 8)}` : ""}
                      </span>
                    )}
                  </span>
                </div>
                <small style={{ color: "#6b705c" }}>{formatDateTime(log.created_at)}</small>
              </div>
            ))}
          </div>
        ) : (
          <p className="empty-state-copy">No recent platform activity recorded.</p>
        )}
      </section>
    </div>
  );
}

function PageHead({
  eyebrow,
  title,
  action,
}: {
  eyebrow: string;
  title: string;
  action?: React.ReactNode;
}) {
  return (
    <header className="admin-heading">
      <div>
        <span className="eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
      </div>
      {action}
    </header>
  );
}

export function AdminArticles() {
  const { categories, loadAdminArticles, approve, reject, publishAny, unpublish, toggleFeatured } =
    useApp();
  const [rows, setRows] = useState<Article[]>([]);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<ArticleStatus | "">("");
  const [category, setCategory] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [rejecting, setRejecting] = useState<Article | null>(null);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState<string | null>(null);
  const location = useLocation();

  // A message handed over by the create form (e.g. "saved as a draft").
  useEffect(() => {
    const handedOver = (location.state as { notice?: string } | null)?.notice;
    if (handedOver) {
      setNotice(handedOver);
      window.history.replaceState({}, document.title);
    }
  }, [location.state]);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setRows(await loadAdminArticles(status));
    } catch (requestError) {
      setRows([]);
      setError(
        requestError instanceof ApiError
          ? requestError.message
          : requestError instanceof ApiUnavailableError
            ? "The MINERVA API is unreachable."
            : "Unable to load the editorial queue.",
      );
    } finally {
      setLoading(false);
    }
  }, [loadAdminArticles, status]);

  useEffect(() => {
    void reload();
  }, [reload]);

  // Client-side narrowing keeps the table responsive; the API remains the
  // authority for what may be shown at all.
  const visible = useMemo(
    () =>
      rows.filter((row) => {
        const matchesSearch = row.title.toLowerCase().includes(search.toLowerCase());
        const matchesCategory = !category || row.category_slug === category;
        return matchesSearch && matchesCategory;
      }),
    [rows, search, category],
  );

  const act = async (id: string, run: () => Promise<EditorialResult>, success: string) => {
    setBusy(id);
    setError(null);
    setNotice(null);
    const result = await run();
    setBusy(null);

    if (!result.ok) {
      setError(result.message);
      return;
    }

    setNotice(success);
    await reload();
  };

  return (
    <div className="admin-page">
      <PageHead
        eyebrow="content desk."
        title="Article management."
        action={
          <Link className="btn" to="/admin/create">
            <Plus /> create article
          </Link>
        }
      />
      <div className="table-tools">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search articles"
          />
        </label>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value as ArticleStatus | "")}
          aria-label="Filter by status"
        >
          <option value="">All statuses</option>
          {Object.entries(articleStatusLabels).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        <select
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          aria-label="Filter by category"
        >
          <option value="">All categories</option>
          {categories.map((item) => (
            <option key={item.id} value={item.slug}>
              {item.name}
            </option>
          ))}
        </select>
      </div>
      {error && <p className="empty-state-copy">{error}</p>}
      {notice && <p className="empty-state-copy">{notice}</p>}
      <div className="data-table article-admin-table">
        <div className="table-head">
          <span>Article</span>
          <span>Category</span>
          <span>Votes</span>
          <span>Status</span>
          <span>Actions</span>
        </div>
        {loading ? (
          <p className="empty-state-copy">Loading the editorial queue…</p>
        ) : visible.length === 0 ? (
          <p className="empty-state-copy">No articles match these filters.</p>
        ) : (
          visible.map((a) => (
            <div className="table-row" key={a.id}>
              <div className="table-article">
                <span>
                  <strong>{a.title}</strong>
                  <small>
                    {a.author} · {a.date}
                  </small>
                </span>
              </div>
              <span>{a.category}</span>
              <strong>{a.votes}</strong>
              <span className={`status ${a.status}`}>{articleStatusLabels[a.status]}</span>
              <div className="row-actions">
                {a.status === "pending_review" && (
                  <>
                    <button
                      aria-label="Approve"
                      title="Approve"
                      disabled={busy === a.id}
                      onClick={() => act(a.id, () => approve(a.id), "Article approved.")}
                    >
                      <Check />
                    </button>
                    <button
                      aria-label="Reject"
                      title="Reject"
                      disabled={busy === a.id}
                      onClick={() => {
                        setRejecting(a);
                        setReason("");
                      }}
                    >
                      <X />
                    </button>
                  </>
                )}
                {(a.status === "approved" || a.status === "draft" || a.status === "unpublished") && (
                  <button
                    aria-label="Publish"
                    title="Publish"
                    disabled={busy === a.id}
                    onClick={() => act(a.id, () => publishAny(a.id), "Article published.")}
                  >
                    <ArrowUpRight />
                  </button>
                )}
                {a.status === "published" && (
                  <button
                    aria-label="Unpublish"
                    title="Unpublish"
                    disabled={busy === a.id}
                    onClick={() => act(a.id, () => unpublish(a.id), "Article unpublished.")}
                  >
                    <EyeOff />
                  </button>
                )}
                <button
                  aria-label={a.is_featured ? "Remove feature" : "Feature"}
                  title={a.is_featured ? "Remove feature" : "Feature"}
                  disabled={busy === a.id}
                  onClick={() =>
                    act(
                      a.id,
                      () => toggleFeatured(a.id, !a.is_featured),
                      a.is_featured ? "Feature removed." : "Article featured.",
                    )
                  }
                >
                  <Star />
                </button>
                <Link to={`/articles/${a.slug || a.id}`} aria-label="View">
                  <Eye />
                </Link>
              </div>
            </div>
          ))
        )}
      </div>
      {rejecting && (
        <div className="modal-backdrop">
          <div className="confirm-modal">
            <button className="modal-close" onClick={() => setRejecting(null)}>
              <X />
            </button>
            <span className="eyebrow">editorial feedback.</span>
            <h2>Send “{rejecting.title}” back?</h2>
            <p>The publisher sees this reason on the rejected article.</p>
            <label className="editor-form">
              <textarea
                rows={4}
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Explain what needs to change before resubmission."
              />
            </label>
            <div>
              <button className="btn outline" onClick={() => setRejecting(null)}>
                cancel
              </button>
              <button
                className="btn"
                disabled={reason.trim().length < 5 || busy === rejecting.id}
                onClick={async () => {
                  const target = rejecting;
                  setRejecting(null);
                  await act(target.id, () => reject(target.id, reason.trim()), "Article rejected.");
                }}
              >
                reject article
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export function CreateArticle() {
  const { categories, createDraft, publishOwn } = useApp();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(false);

  const submit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const action = String(data.get("action") ?? "draft");

    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    const result: EditorialResult = await createDraft({
      title: String(data.get("title") ?? ""),
      abstract: String(data.get("abstract") ?? ""),
      body: String(data.get("body") ?? ""),
      category_id: String(data.get("category_id") ?? ""),
    });

    if (!result.ok) {
      setError(result.message);
      setFieldErrors(result.fieldErrors ?? {});
      setSubmitting(false);
      return;
    }

    if (action === "publish") {
      // Direct publishing needs the article.publish_own permission; when it
      // is missing the article simply stays a draft.
      const publishResult = await publishOwn(result.id ?? "");
      if (!publishResult.ok) {
        setSubmitting(false);
        navigate("/admin/articles", {
          state: {
            notice: `${publishResult.message} The article was saved as a draft instead.`,
          },
        });
        return;
      }
    }

    setSubmitting(false);
    navigate("/admin/articles");
  };

  return (
    <div className="admin-page">
      <PageHead eyebrow="content desk." title="Create a new article." />
      {error && <p className="empty-state-copy">{error}</p>}
      <form className="editor-form" onSubmit={submit}>
        <section>
          <header>
            <span>01</span>
            <div>
              <h2>Basic information</h2>
              <p>Set the title, summary and author attribution.</p>
            </div>
          </header>
          <label>
            Article title
            <input name="title" required placeholder="A clear, compelling title" />
            {fieldErrors.title && <small className="field-error">{fieldErrors.title[0]}</small>}
          </label>
          <label>
            Short description
            <textarea
              name="abstract"
              required
              rows={3}
              placeholder="A concise introduction for cards and search results."
            />
            {fieldErrors.abstract && <small className="field-error">{fieldErrors.abstract[0]}</small>}
          </label>
          <div className="two-col">
            <label>
              Author(s)
              <input name="author" placeholder="Full name" disabled />
              <small className="field-hint">Taken from your account.</small>
            </label>
            <label>
              Category
              <select name="category_id" required defaultValue="">
                <option value="" disabled>
                  Choose a category
                </option>
                {categories.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
              {fieldErrors.category_id && (
                <small className="field-error">{fieldErrors.category_id[0]}</small>
              )}
            </label>
          </div>
          <label>
            Article content
            <textarea
              name="body"
              required
              rows={10}
              placeholder="Write or paste the full article here…"
            />
            {fieldErrors.body && <small className="field-error">{fieldErrors.body[0]}</small>}
          </label>
        </section>
        <section>
          <header>
            <span>02</span>
            <div>
              <h2>Publication</h2>
              <p>Save privately or publish immediately when you hold the direct-publish grant.</p>
            </div>
          </header>
          <div className="publish-actions">
            <button className="btn outline" name="action" value="draft" disabled={submitting}>
              {submitting ? "saving…" : "save as draft"}
            </button>
            <button className="btn" name="action" value="publish" disabled={submitting}>
              publish article
            </button>
          </div>
        </section>
      </form>
    </div>
  );
}

export function VotingManagement() {
  const { refreshVotingStatus } = useApp();
  const [periods, setPeriods] = useState<VotingPeriodDto[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [startsAt, setStartsAt] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const [activateOnCreate, setActivateOnCreate] = useState(true);
  const [creating, setCreating] = useState(false);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");
  const [editStartsAt, setEditStartsAt] = useState("");
  const [editEndsAt, setEditEndsAt] = useState("");

  const loadPeriods = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setPeriods(await getAdminVotingPeriods());
    } catch (err) {
      setError(
        err instanceof ApiUnavailableError
          ? "The MINERVA API is unreachable."
          : err instanceof ApiError
            ? err.message
            : "Unable to load voting periods.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadPeriods();
  }, [loadPeriods]);

  const handleCreate = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setNotice(null);

    const startDate = new Date(startsAt);
    const endDate = new Date(endsAt);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate <= startDate) {
      setError("End date and time must be strictly after the start date and time.");
      return;
    }

    if (
      !window.confirm(
        `Create voting period "${name}"${activateOnCreate ? " and set it as active" : ""}?`,
      )
    ) {
      return;
    }

    setCreating(true);
    try {
      await createAdminVotingPeriod({
        name: name.trim(),
        starts_at: startDate.toISOString(),
        ends_at: endDate.toISOString(),
        is_active: activateOnCreate,
      });
      setName("");
      setStartsAt("");
      setEndsAt("");
      setNotice("Voting period created.");
      await Promise.all([loadPeriods(), refreshVotingStatus()]);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to create voting period.");
    } finally {
      setCreating(false);
    }
  };

  const handleActivate = async (period: VotingPeriodDto) => {
    if (!window.confirm(`Activate "${period.name}"? Any other active voting period will be deactivated.`)) {
      return;
    }
    setBusyId(period.id);
    setError(null);
    setNotice(null);
    try {
      await activateAdminVotingPeriod(period.id);
      setNotice(`"${period.name}" is now the active voting period.`);
      await Promise.all([loadPeriods(), refreshVotingStatus()]);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to activate voting period.");
    } finally {
      setBusyId(null);
    }
  };

  const startEdit = (period: VotingPeriodDto) => {
    setEditingId(period.id);
    setEditName(period.name);
    setEditStartsAt(toDateTimeLocal(period.starts_at));
    setEditEndsAt(toDateTimeLocal(period.ends_at));
    setError(null);
    setNotice(null);
  };

  const handleUpdate = async (event: FormEvent, periodId: string) => {
    event.preventDefault();
    const startDate = new Date(editStartsAt);
    const endDate = new Date(editEndsAt);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate <= startDate) {
      setError("End date and time must be strictly after the start date and time.");
      return;
    }
    if (!window.confirm("Save changes to this voting period?")) return;

    setBusyId(periodId);
    setError(null);
    setNotice(null);
    try {
      await updateAdminVotingPeriod(periodId, {
        name: editName.trim(),
        starts_at: startDate.toISOString(),
        ends_at: endDate.toISOString(),
      });
      setEditingId(null);
      setNotice("Voting period updated.");
      await Promise.all([loadPeriods(), refreshVotingStatus()]);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to update voting period.");
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="admin-page">
      <PageHead eyebrow="ballot control." title="Voting management." />
      {notice && (
        <div className="notice-banner" role="status">
          <span>{notice}</span>
          <button type="button" onClick={() => setNotice(null)} aria-label="Dismiss notice">
            <X size={14} />
          </button>
        </div>
      )}
      {error && (
        <div className="error-banner" role="alert">
          <span>{error}</span>
          <button type="button" onClick={() => setError(null)} aria-label="Dismiss error">
            <X size={14} />
          </button>
        </div>
      )}

      <form className="editor-form" onSubmit={handleCreate}>
        <section>
          <header>
            <span>01</span>
            <div>
              <h2>Configure voting window</h2>
              <p>Define server-enforced start and end timestamps for participant ballots.</p>
            </div>
          </header>
          <label>
            Period name
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              placeholder="e.g. Minerva 2026 Competition Ballot"
            />
          </label>
          <div className="two-col">
            <label>
              Starts at
              <input
                type="datetime-local"
                value={startsAt}
                onChange={(e) => setStartsAt(e.target.value)}
                required
              />
            </label>
            <label>
              Ends at
              <input
                type="datetime-local"
                value={endsAt}
                onChange={(e) => setEndsAt(e.target.value)}
                required
              />
            </label>
          </div>
          <label className="toggle-row">
            <span>Activate immediately upon creation</span>
            <input
              type="checkbox"
              checked={activateOnCreate}
              onChange={(e) => setActivateOnCreate(e.target.checked)}
            />
          </label>
          <div className="publish-actions">
            <button className="btn" type="submit" disabled={creating}>
              <Plus /> {creating ? "creating…" : "create voting period"}
            </button>
          </div>
        </section>
      </form>

      <section style={{ marginTop: "2rem" }}>
        {loading ? (
          <p className="empty-state-copy">Loading voting periods…</p>
        ) : periods.length === 0 ? (
          <p className="empty-state-copy">No voting periods configured yet.</p>
        ) : (
          <div className="voting-grid">
            {periods.map((period) => (
              <article key={period.id}>
                <div>
                  <span className={`status ${period.status}`}>{period.status}</span>
                  {period.is_active && (
                    <span className="status open" style={{ marginLeft: "0.5rem" }}>
                      active
                    </span>
                  )}
                  {editingId === period.id ? (
                    <form onSubmit={(e) => void handleUpdate(e, period.id)}>
                      <label>
                        Name
                        <input
                          value={editName}
                          onChange={(e) => setEditName(e.target.value)}
                          required
                        />
                      </label>
                      <label>
                        Starts at
                        <input
                          type="datetime-local"
                          value={editStartsAt}
                          onChange={(e) => setEditStartsAt(e.target.value)}
                          required
                        />
                      </label>
                      <label>
                        Ends at
                        <input
                          type="datetime-local"
                          value={editEndsAt}
                          onChange={(e) => setEditEndsAt(e.target.value)}
                          required
                        />
                      </label>
                      <div className="publish-actions" style={{ marginTop: "0.75rem" }}>
                        <button className="btn" type="submit" disabled={busyId === period.id}>
                          save
                        </button>
                        <button
                          className="btn outline"
                          type="button"
                          onClick={() => setEditingId(null)}
                        >
                          cancel
                        </button>
                      </div>
                    </form>
                  ) : (
                    <>
                      <h3>{period.name}</h3>
                      <p>
                        {formatDateTime(period.starts_at)} — {formatDateTime(period.ends_at)}
                      </p>
                    </>
                  )}
                </div>
                {editingId !== period.id && (
                  <div style={{ display: "flex", gap: "0.5rem", flexWrap: "wrap" }}>
                    <button
                      className="btn outline"
                      type="button"
                      onClick={() => startEdit(period)}
                      disabled={busyId === period.id}
                    >
                      <Edit3 size={14} /> edit
                    </button>
                    {!period.is_active && (
                      <button
                        className="btn"
                        type="button"
                        onClick={() => void handleActivate(period)}
                        disabled={busyId === period.id}
                      >
                        <Check size={14} /> activate
                      </button>
                    )}
                  </div>
                )}
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}

export function Results() {
  const { articles } = useApp();
  const [statistics, setStatistics] = useState<VotingStatisticsDto | null>(null);
  const [analytics, setAnalytics] = useState<AdminAnalyticsDto | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    const loadStats = () => {
      Promise.allSettled([
        getAdminVotingStatistics(controller.signal),
        getAdminAnalytics(controller.signal),
      ]).then(([votingRes, analyticsRes]) => {
        if (controller.signal.aborted) return;
        if (votingRes.status === "fulfilled") {
          setStatistics(votingRes.value);
          setError(null);
        }
        if (analyticsRes.status === "fulfilled") {
          setAnalytics(analyticsRes.value);
          setError(null);
        }
        if (votingRes.status === "rejected" && analyticsRes.status === "rejected") {
          const err = votingRes.reason;
          setError(
            err instanceof ApiUnavailableError
              ? "The MINERVA API is unreachable."
              : err instanceof ApiError
                ? err.message
                : "Unable to load voting statistics.",
          );
        }
      });
    };

    loadStats();
    const interval = window.setInterval(loadStats, 20000);

    return () => {
      controller.abort();
      window.clearInterval(interval);
    };
  }, []);

  const fallbackSorted = [...articles]
    .filter((a) => a.status === "published")
    .sort((a, b) => b.votes - a.votes);

  const rows = analytics?.top_performing_articles?.length
    ? analytics.top_performing_articles.map((entry) => ({
        id: entry.id,
        title: entry.title,
        category: entry.category?.name ?? "Uncategorized",
        votes: entry.votes_count,
      }))
    : statistics
      ? statistics.top_articles.map((entry) => ({
          id: entry.article_id,
          title: entry.title,
          category: entry.category?.name ?? "Uncategorized",
          votes: entry.vote_count,
        }))
      : fallbackSorted.map((a) => ({
          id: a.id,
          title: a.title,
          category: a.category,
          votes: a.votes,
        }));

  const total =
    analytics?.voting.total_votes ??
    statistics?.total_votes ??
    rows.reduce((s, a) => s + a.votes, 0);

  const uniqueVoters =
    analytics?.voting.unique_voters ??
    statistics?.unique_voters ??
    0;

  const publishedCount =
    analytics?.articles_summary.by_status.published ??
    statistics?.published_articles_count ??
    fallbackSorted.length;

  const pie = analytics?.categories?.length
    ? analytics.categories
        .filter((item) => item.votes_count > 0)
        .map((item) => ({ name: item.name, value: item.votes_count }))
    : (statistics?.by_category ?? [])
        .filter((item) => item.votes > 0)
        .map((item) => ({ name: item.name, value: item.votes }));
  const pieColors = ["#8A6D3B", "#2E2A21", "#C9A227", "#6B705C", "#A58A5B"];

  return (
    <div className="admin-page">
      <PageHead eyebrow="the results." title="The count, made visible." />
      {error && (
        <div className="error-banner" role="alert">
          <span>{error}</span>
        </div>
      )}
      <div className="result-summary">
        <div>
          <strong>{total.toLocaleString()}</strong>
          <span>votes cast</span>
        </div>
        <div>
          <strong>{uniqueVoters.toLocaleString()}</strong>
          <span>unique voters</span>
        </div>
        <div>
          <strong>{publishedCount.toLocaleString()}</strong>
          <span>published entries</span>
        </div>
      </div>
      <div className="charts-grid">
        <section>
          <header>
            <div>
              <span className="eyebrow">ranking.</span>
              <h2>Article performance</h2>
            </div>
          </header>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart
              data={rows
                .slice(0, 6)
                .map((a) => ({
                  name: a.title.slice(0, 18) + (a.title.length > 18 ? "…" : ""),
                  votes: a.votes,
                }))}
              layout="vertical"
            >
              <XAxis type="number" hide />
              <YAxis dataKey="name" type="category" width={130} axisLine={false} tickLine={false} />
              <Tooltip />
              <Bar dataKey="votes" fill="#8A6D3B" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </section>
        <section>
          <header>
            <div>
              <span className="eyebrow">distribution.</span>
              <h2>Votes by field</h2>
            </div>
          </header>
          {pie.length > 0 ? (
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie data={pie} dataKey="value" innerRadius={72} outerRadius={110} paddingAngle={3}>
                  {pie.map((entry, idx) => (
                    <Cell key={entry.name} fill={pieColors[idx % pieColors.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <p className="empty-state-copy">No category vote distribution yet.</p>
          )}
        </section>
      </div>
      <div className="results-table">
        {rows.map((a, i) => (
          <div className={i === 0 && a.votes > 0 ? "winner" : ""} key={a.id}>
            <strong>{String(i + 1).padStart(2, "0")}</strong>
            <span>
              <b>{a.title}</b>
              <small>{a.category}</small>
            </span>
            <em>{total > 0 ? ((a.votes / total) * 100).toFixed(1) : "0.0"}%</em>
            <b>{a.votes}</b>
            {i === 0 && a.votes > 0 && <i>Most Popular · Leading</i>}
          </div>
        ))}
      </div>
    </div>
  );
}

export function Participants() {
  const [inactive, setInactive] = useState<string[]>([]);
  const [search, setSearch] = useState("");
  return (
    <div className="admin-page">
      <PageHead eyebrow="community." title="Participant management." />
      <div className="table-tools">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search participants"
          />
        </label>
      </div>
      <div className="data-table participant-table">
        <div className="table-head">
          <span>Participant</span>
          <span>Faculty</span>
          <span>Votes</span>
          <span>Status</span>
          <span>Action</span>
        </div>
        {participants
          .filter((p) => p[0].toString().toLowerCase().includes(search.toLowerCase()))
          .map((p) => (
            <div className="table-row" key={p[0]}>
              <div className="participant-name">
                <span>
                  {p[0]
                    .toString()
                    .split(" ")
                    .map((n) => n[0])
                    .slice(0, 2)
                    .join("")}
                </span>
                <div>
                  <strong>{p[0]}</strong>
                  <small>{p[1]}</small>
                </div>
              </div>
              <span>{p[2]}</span>
              <strong>{p[3]}</strong>
              <span className={`status ${inactive.includes(p[0] as string) ? "closed" : "open"}`}>
                {inactive.includes(p[0] as string) ? "inactive" : "active"}
              </span>
              <button
                className="text-button"
                onClick={() =>
                  setInactive((c) =>
                    c.includes(p[0] as string)
                      ? c.filter((n) => n !== p[0])
                      : [...c, p[0] as string],
                  )
                }
              >
                {inactive.includes(p[0] as string) ? "Activate" : "Deactivate"}
              </button>
            </div>
          ))}
      </div>
    </div>
  );
}

export function AdminSettings() {
  return (
    <div className="admin-page">
      <PageHead eyebrow="configuration." title="Platform settings." />
      <div className="settings-grid">
        <section>
          <h2>Editor profile</h2>
          <label>
            Display name
            <input placeholder="Editor name" />
          </label>
          <label>
            Contact email
            <input placeholder="name@example.com" />
          </label>
          <button className="btn">save profile</button>
        </section>
        <section>
          <h2>Notification preferences</h2>
          {[
            "New participant registrations",
            "New votes recorded",
            "Article status changes",
            "Weekly results digest",
          ].map((item) => (
            <label className="toggle-row" key={item}>
              <span>{item}</span>
              <input type="checkbox" defaultChecked />
            </label>
          ))}
        </section>
        <section className="platform-info">
          <span>MINERVA</span>
          <h2>Editorial Platform</h2>
          <p>
            IEEE Engineering in Medicine and Biology Society Student Branch Chapter, University of
            Sri Jayewardenepura.
          </p>
          <small>Backend integration ready</small>
        </section>
      </div>
    </div>
  );
}

export function AdminComments() {
  const [comments, setComments] = useState<CommentDto[]>([]);
  const [statusFilter, setStatusFilter] = useState<CommentStatus | "">("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [targetComment, setTargetComment] = useState<CommentDto | null>(null);
  const [reason, setReason] = useState("");
  const [removing, setRemoving] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);

  const loadComments = useCallback(
    async (signal?: AbortSignal) => {
      setLoading(true);
      setError(null);
      try {
        const res = await getAdminComments({ status: statusFilter || undefined }, signal);
        if (!signal?.aborted) setComments(res.data);
      } catch (err) {
        if (signal?.aborted) return;
        setError(err instanceof Error ? err.message : "Unable to load comments.");
      } finally {
        if (!signal?.aborted) setLoading(false);
      }
    },
    [statusFilter],
  );

  useEffect(() => {
    const controller = new AbortController();
    void loadComments(controller.signal);
    return () => controller.abort();
  }, [loadComments]);

  const handleConfirmRemove = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!targetComment) return;

    const trimmed = reason.trim();
    if (trimmed.length < 5) {
      setModalError("Please provide a moderation reason of at least 5 characters.");
      return;
    }

    setRemoving(true);
    setModalError(null);

    try {
      const updated = await moderateRemoveComment(targetComment.id, trimmed);
      setComments((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
      setNotice("Comment removed and audit record saved.");
      setTargetComment(null);
      setReason("");
    } catch (err) {
      if (err instanceof ApiError) {
        setModalError(err.fieldError("reason") || err.message);
      } else {
        setModalError(err instanceof Error ? err.message : "Unable to remove comment.");
      }
    } finally {
      setRemoving(false);
    }
  };

  return (
    <div className="admin-page">
      <PageHead eyebrow="moderation." title="Comments Moderation." />

      {notice && (
        <div className="notice-banner" role="status" style={{ marginBottom: "1rem" }}>
          <span>{notice}</span>
        </div>
      )}

      {error && (
        <div className="error-banner" role="alert" style={{ marginBottom: "1rem" }}>
          <span>{error}</span>
        </div>
      )}

      <div style={{ display: "flex", gap: "0.5rem", marginBottom: "1.25rem", flexWrap: "wrap" }}>
        {(
          [
            ["", "All"],
            ["visible", "Visible"],
            ["removed", "Removed"],
          ] as const
        ).map(([val, label]) => (
          <button
            key={label}
            type="button"
            className={statusFilter === val ? "btn" : "btn outline"}
            onClick={() => setStatusFilter(val)}
          >
            {label}
          </button>
        ))}
      </div>

      {loading ? (
        <p className="empty-state-copy">Loading comments…</p>
      ) : comments.length === 0 ? (
        <p className="empty-state-copy">No comments found for this filter.</p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Comment</th>
                <th>Author</th>
                <th>Article</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {comments.map((comment) => {
                const latestReason = comment.moderation_records?.[0]?.reason;
                return (
                  <tr key={comment.id}>
                    <td style={{ maxWidth: "320px" }}>
                      <p style={{ margin: 0, whiteSpace: "pre-wrap" }}>
                        {comment.body.length > 140 ? `${comment.body.slice(0, 140)}…` : comment.body}
                      </p>
                      {latestReason && (
                        <small className="field-hint">Reason: {latestReason}</small>
                      )}
                    </td>
                    <td>{comment.author?.name ?? "—"}</td>
                    <td>
                      {comment.article ? (
                        <Link to={`/articles/${comment.article.slug}`}>
                          {comment.article.title}
                        </Link>
                      ) : (
                        "—"
                      )}
                    </td>
                    <td>{comment.created_at ? formatDateTime(comment.created_at) : "—"}</td>
                    <td>
                      <span className={`status ${comment.status}`}>{comment.status}</span>
                    </td>
                    <td>
                      {!comment.is_removed ? (
                        <button
                          type="button"
                          className="btn outline"
                          onClick={() => {
                            setTargetComment(comment);
                            setReason("");
                            setModalError(null);
                          }}
                        >
                          <Trash2 size={14} /> remove
                        </button>
                      ) : (
                        <small className="field-hint">Removed</small>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {targetComment && (
        <div className="modal-backdrop">
          <div className="confirm-modal">
            <button
              className="modal-close"
              disabled={removing}
              onClick={() => setTargetComment(null)}
            >
              <X />
            </button>
            <span className="eyebrow">moderation action.</span>
            <h3>Remove comment</h3>
            <p style={{ opacity: 0.8 }}>
              Author: <strong>{targetComment.author?.name ?? "Reader"}</strong>
            </p>
            <form onSubmit={(e) => void handleConfirmRemove(e)} style={{ display: "grid", gap: "0.75rem" }}>
              {modalError && (
                <div className="error-banner" role="alert">
                  <span>{modalError}</span>
                </div>
              )}
              <label style={{ display: "grid", gap: "0.4rem" }}>
                Mandatory removal reason (min 5 chars)
                <textarea
                  rows={3}
                  required
                  minLength={5}
                  maxLength={1000}
                  disabled={removing}
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  placeholder="Explain why this comment is being removed…"
                />
              </label>
              <div style={{ display: "flex", gap: "0.6rem", justifyContent: "flex-end" }}>
                <button
                  type="button"
                  className="btn outline"
                  disabled={removing}
                  onClick={() => setTargetComment(null)}
                >
                  cancel
                </button>
                <button type="submit" className="btn" disabled={removing}>
                  <MessageSquare size={14} /> {removing ? "removing…" : "confirm removal"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export function AdminAuditLogs() {
  const [logs, setLogs] = useState<AuditLogDto[]>([]);
  const [meta, setMeta] = useState<PaginatedAuditLogs["meta"]>({
    current_page: 1,
    per_page: 25,
    total: 0,
  });
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [actionCategory, setActionCategory] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [inspectingLog, setInspectingLog] = useState<AuditLogDto | null>(null);

  const fetchLogs = useCallback(
    async (targetPage: number) => {
      setLoading(true);
      setError(null);
      try {
        const res = await getAdminAuditLogs({
          page: targetPage,
          per_page: 25,
          search: search.trim() || undefined,
          action: actionCategory ? `${actionCategory}*` : undefined,
        });
        setLogs(res.data);
        setMeta(res.meta);
      } catch (err) {
        setLogs([]);
        setError(
          err instanceof ApiError
            ? err.message
            : err instanceof ApiUnavailableError
              ? "The MINERVA API is unreachable."
              : "Unable to load audit logs.",
        );
      } finally {
        setLoading(false);
      }
    },
    [search, actionCategory],
  );

  useEffect(() => {
    void fetchLogs(page);
  }, [fetchLogs, page]);

  const categories = [
    { label: "All actions", value: "" },
    { label: "Articles", value: "article." },
    { label: "Comments", value: "comment." },
    { label: "Voting", value: "voting." },
    { label: "Auth & Identity", value: "identity." },
  ];

  return (
    <div className="admin-page">
      <PageHead
        eyebrow="governance & accountability."
        title="Audit trail."
      />

      <div
        className="table-tools"
        style={{ display: "flex", gap: "1rem", flexWrap: "wrap", alignItems: "center" }}
      >
        <label style={{ flex: "1 1 280px" }}>
          <Search />
          <input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Search action, actor, or subject…"
          />
        </label>
        <div style={{ display: "flex", gap: "0.4rem", flexWrap: "wrap" }}>
          {categories.map((cat) => (
            <button
              key={cat.label}
              type="button"
              className={actionCategory === cat.value ? "btn" : "btn outline"}
              style={{ fontSize: "0.82rem", padding: "0.35rem 0.75rem" }}
              onClick={() => {
                setActionCategory(cat.value);
                setPage(1);
              }}
            >
              {cat.label}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div className="error-banner" role="alert" style={{ margin: "1rem 0" }}>
          <span>{error}</span>
        </div>
      )}

      <div className="data-table" style={{ marginTop: "1rem" }}>
        <div
          className="table-head"
          style={{
            display: "grid",
            gridTemplateColumns: "180px 220px 200px 180px 100px 1fr",
            gap: "0.75rem",
          }}
        >
          <span>Timestamp</span>
          <span>Action</span>
          <span>Actor</span>
          <span>Subject</span>
          <span>IP Hash</span>
          <span style={{ textAlign: "right" }}>Inspect</span>
        </div>

        {loading ? (
          <p className="empty-state-copy">Loading audit records…</p>
        ) : logs.length === 0 ? (
          <p className="empty-state-copy">No audit records match the selected filters.</p>
        ) : (
          logs.map((log) => (
            <div
              key={log.id}
              className="table-row"
              style={{
                display: "grid",
                gridTemplateColumns: "180px 220px 200px 180px 100px 1fr",
                gap: "0.75rem",
                alignItems: "center",
                padding: "0.85rem 1rem",
              }}
            >
              <small style={{ color: "#6b705c" }}>{formatDateTime(log.created_at)}</small>
              <div>
                <code
                  style={{
                    fontSize: "0.78rem",
                    background: "rgba(138, 109, 59, 0.12)",
                    color: "#8a6d3b",
                    padding: "2px 6px",
                    borderRadius: "4px",
                    fontWeight: 600,
                  }}
                >
                  {log.action}
                </code>
              </div>
              <div style={{ display: "flex", flexDirection: "column", fontSize: "0.85rem" }}>
                <strong>{log.actor?.name || "System"}</strong>
                {log.actor?.email && <small style={{ opacity: 0.7 }}>{log.actor.email}</small>}
              </div>
              <div style={{ fontSize: "0.82rem", opacity: 0.85 }}>
                {log.subject_type ? (
                  <span>
                    {log.subject_type.split("\\").pop()}{" "}
                    {log.subject_id ? `#${log.subject_id.slice(0, 8)}` : ""}
                  </span>
                ) : (
                  <span style={{ opacity: 0.5 }}>—</span>
                )}
              </div>
              <div>
                {log.ip_hash ? (
                  <code style={{ fontSize: "0.72rem", opacity: 0.7 }}>
                    {log.ip_hash.slice(0, 8)}…
                  </code>
                ) : (
                  <span style={{ opacity: 0.5 }}>—</span>
                )}
              </div>
              <div style={{ textAlign: "right" }}>
                <button
                  type="button"
                  className="btn outline"
                  style={{ fontSize: "0.78rem", padding: "0.25rem 0.6rem" }}
                  onClick={() => setInspectingLog(log)}
                >
                  <Eye size={13} /> view
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {meta.total > meta.per_page && (
        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            marginTop: "1.5rem",
          }}
        >
          <span style={{ fontSize: "0.85rem", opacity: 0.75 }}>
            Showing page {meta.current_page} of {Math.ceil(meta.total / meta.per_page)} (
            {meta.total} total events)
          </span>
          <div style={{ display: "flex", gap: "0.5rem" }}>
            <button
              type="button"
              className="btn outline"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              previous
            </button>
            <button
              type="button"
              className="btn outline"
              disabled={page >= Math.ceil(meta.total / meta.per_page) || loading}
              onClick={() => setPage((p) => p + 1)}
            >
              next
            </button>
          </div>
        </div>
      )}

      {inspectingLog && (
        <div className="modal-backdrop" onClick={() => setInspectingLog(null)}>
          <div
            className="modal-content"
            style={{ maxWidth: "650px", width: "90%" }}
            onClick={(e) => e.stopPropagation()}
          >
            <header
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                marginBottom: "1rem",
              }}
            >
              <div>
                <span className="eyebrow">audit record.</span>
                <h2>Event inspection</h2>
              </div>
              <button
                type="button"
                className="btn outline"
                onClick={() => setInspectingLog(null)}
              >
                <X size={15} />
              </button>
            </header>

            <div
              style={{
                display: "grid",
                gap: "0.75rem",
                fontSize: "0.88rem",
                marginBottom: "1.2rem",
              }}
            >
              <div>
                <small
                  style={{
                    textTransform: "uppercase",
                    letterSpacing: "0.06em",
                    opacity: 0.7,
                  }}
                >
                  Action
                </small>
                <div>
                  <strong>{inspectingLog.action}</strong>
                </div>
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                <div>
                  <small
                    style={{
                      textTransform: "uppercase",
                      letterSpacing: "0.06em",
                      opacity: 0.7,
                    }}
                  >
                    Actor
                  </small>
                  <div>
                    {inspectingLog.actor?.name ?? "System"} (
                    {inspectingLog.actor?.email ?? "system"})
                  </div>
                </div>
                <div>
                  <small
                    style={{
                      textTransform: "uppercase",
                      letterSpacing: "0.06em",
                      opacity: 0.7,
                    }}
                  >
                    Recorded At
                  </small>
                  <div>{formatDateTime(inspectingLog.created_at)}</div>
                </div>
              </div>
              {inspectingLog.subject_type && (
                <div>
                  <small
                    style={{
                      textTransform: "uppercase",
                      letterSpacing: "0.06em",
                      opacity: 0.7,
                    }}
                  >
                    Subject
                  </small>
                  <div>
                    <code>{inspectingLog.subject_type}</code> · ID:{" "}
                    <code>{inspectingLog.subject_id}</code>
                  </div>
                </div>
              )}
            </div>

            <div style={{ display: "grid", gap: "0.4rem" }}>
              <small
                style={{
                  textTransform: "uppercase",
                  letterSpacing: "0.06em",
                  opacity: 0.7,
                }}
              >
                Metadata Payload
              </small>
              <pre
                style={{
                  background: "#1e1e1e",
                  color: "#d4d4d4",
                  padding: "1rem",
                  borderRadius: "6px",
                  fontSize: "0.8rem",
                  overflowX: "auto",
                  maxHeight: "260px",
                }}
              >
                {JSON.stringify(inspectingLog.metadata, null, 2)}
              </pre>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
