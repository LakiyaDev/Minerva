import { useCallback, useEffect, useRef, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Check,
  Download,
  Feather,
  Grid2X2,
  List,
  Medal,
  Search,
  SlidersHorizontal,
  X,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { ArticleCard } from "../components/ArticleCard";
import { CommentSection } from "../components/CommentSection";
import { mapArticle, useApp } from "../store";
import {
  getArticle,
  getArticles,
  getAttachmentDownloadUrl,
  formatAttachmentSize,
  type ArticleDetailDto,
  type ArticleSummaryDto,
} from "../services/articleService";
import {
  getArticleVoteResults,
  getLeaderboard,
  type ArticleVoteResultDto,
  type LeaderboardEntryDto,
} from "../services/votingService";
import { subscribeVotingResults } from "../services/echoService";
import { ApiError, ApiUnavailableError } from "../services/apiClient";

const PAGE_SIZE = 12;

export function Articles() {
  const { categories } = useApp();
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [category, setCategory] = useState("");
  const [sort, setSort] = useState<"newest" | "popular">("newest");
  const [list, setList] = useState(false);
  const [items, setItems] = useState<ArticleSummaryDto[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadToken, setReloadToken] = useState(0);
  // Guards against a slow earlier response overwriting a newer one.
  const requestSeq = useRef(0);

  // Debounce the search term so typing does not hammer the API.
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timer);
  }, [search]);

  // Any filter change restarts pagination from the first page.
  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, category, sort]);

  useEffect(() => {
    const controller = new AbortController();
    const seq = ++requestSeq.current;
    const firstPage = page === 1;

    if (firstPage) setLoading(true);
    setError(null);

    getArticles(
      {
        search: debouncedSearch || undefined,
        category: category || undefined,
        sort,
        page,
        per_page: PAGE_SIZE,
      },
      controller.signal,
    )
      .then((response) => {
        if (seq !== requestSeq.current) return;
        setItems((current) => (firstPage ? response.data : [...current, ...response.data]));
        setTotal(response.meta?.total ?? response.data.length);
      })
      .catch((requestError) => {
        if (seq !== requestSeq.current || controller.signal.aborted) return;
        setError(
          requestError instanceof ApiUnavailableError
            ? "The MINERVA API is unreachable. Start the backend to browse the live library."
            : requestError instanceof ApiError
              ? requestError.message
              : "Unable to load articles right now.",
        );
      })
      .finally(() => {
        if (seq !== requestSeq.current) return;
        setLoading(false);
        setLoadingMore(false);
      });

    return () => controller.abort();
  }, [debouncedSearch, category, sort, page, reloadToken]);

  const hasMore = items.length < total;

  return (
    <div className="page">
      <header className="editorial-header">
        <span className="eyebrow">the collected work · {total} essays</span>
        <h1>the library.</h1>
        <p>
          Original student perspectives on medicine, technology, living systems and the
          responsibility that connects them.
        </p>
      </header>
      <div className="filter-bar">
        <label className="search">
          <Search size={18} />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search title or abstract"
            aria-label="Search articles"
          />
        </label>
        <div className="chips">
          <button className={category === "" ? "active" : ""} onClick={() => setCategory("")}>
            All
          </button>
          {categories.map((item) => (
            <button
              key={item.id}
              className={category === item.slug ? "active" : ""}
              onClick={() => setCategory(item.slug)}
            >
              {item.name}
            </button>
          ))}
        </div>
        <select
          value={sort}
          onChange={(e) => setSort(e.target.value as "newest" | "popular")}
          aria-label="Sort articles"
        >
          <option value="newest">Newest</option>
          <option value="popular">Featured first</option>
        </select>
        <div className="view-toggle">
          <button
            className={!list ? "active" : ""}
            onClick={() => setList(false)}
            aria-label="Grid view"
          >
            <Grid2X2 size={17} />
          </button>
          <button
            className={list ? "active" : ""}
            onClick={() => setList(true)}
            aria-label="List view"
          >
            <List size={18} />
          </button>
        </div>
      </div>
      {loading ? (
        <div className="empty-state">
          <span>◉</span>
          <h2>gathering the collection.</h2>
          <p>Loading published articles from the editorial desk.</p>
        </div>
      ) : error ? (
        <div className="empty-state">
          <span>◉</span>
          <h2>the library is unavailable.</h2>
          <p>{error}</p>
          <button onClick={() => setReloadToken((current) => current + 1)}>retry</button>
        </div>
      ) : items.length ? (
        <>
          <div className={`article-grid ${list ? "list" : ""}`}>
            {items.map((a, i) => (
              <ArticleCard
                article={mapArticle(a)}
                index={i + 1}
                key={a.id}
                compact={list}
              />
            ))}
          </div>
          {hasMore && (
            <button
              className="load-more"
              disabled={loadingMore}
              onClick={() => {
                setLoadingMore(true);
                setPage((current) => current + 1);
              }}
            >
              {loadingMore ? "loading…" : "load more articles"} <ArrowRight size={16} />
            </button>
          )}
        </>
      ) : (
        <div className="empty-state">
          <span>◉</span>
          <h2>no articles match your search.</h2>
          <p>Try another phrase or clear your filters.</p>
          <button
            onClick={() => {
              setSearch("");
              setCategory("");
            }}
          >
            clear filters
          </button>
        </div>
      )}
    </div>
  );
}

export function ArticleDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { articles, role, voted, votingStatus, requestVote, castVote } = useApp();
  const [article, setArticle] = useState<ArticleDetailDto | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [confirm, setConfirm] = useState(false);
  const [success, setSuccess] = useState(false);
  const [liveResult, setLiveResult] = useState<ArticleVoteResultDto | null>(null);
  const [submittingVote, setSubmittingVote] = useState(false);
  const [voteError, setVoteError] = useState<string | null>(null);

  useEffect(() => {
    setVoteError(null);
    setConfirm(false);
  }, [id]);

  useEffect(() => {
    if (!id) return;

    const controller = new AbortController();
    setLoading(true);
    setError(null);

    getArticle(id, controller.signal)
      .then((response) => setArticle(response))
      .catch((requestError) => {
        if (controller.signal.aborted) return;
        setError(
          requestError instanceof ApiError && requestError.status === 404
            ? "This article is not published."
            : requestError instanceof ApiUnavailableError
              ? "The MINERVA API is unreachable."
              : "Unable to load this article right now.",
        );
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [id]);

  useEffect(() => {
    if (role === "guest" || !article?.id) {
      setLiveResult(null);
      return;
    }

    const articleId = article.id;
    const controller = new AbortController();

    const fetchResults = () => {
      getArticleVoteResults(articleId, controller.signal)
        .then((result) => {
          if (!controller.signal.aborted) setLiveResult(result);
        })
        .catch(() => {
          // Retain last known result if background polling fails.
        });
    };

    fetchResults();

    const interval = votingStatus?.is_open ? window.setInterval(fetchResults, 20000) : undefined;

    const periodId = votingStatus?.period?.id;
    const unsubscribe = periodId
      ? subscribeVotingResults(periodId, (payload) => {
          if (payload.article_id === articleId) {
            setLiveResult((prev) =>
              prev ? { ...prev, vote_count: payload.vote_count } : prev,
            );
          }
        })
      : () => {};

    return () => {
      controller.abort();
      if (interval !== undefined) window.clearInterval(interval);
      unsubscribe();
    };
  }, [role, article?.id, votingStatus?.is_open]);

  if (loading) {
    return (
      <div className="empty-state">
        <span>◉</span>
        <h2>opening the article.</h2>
        <p>Fetching the published record from the editorial desk.</p>
      </div>
    );
  }

  if (error || !article) {
    return (
      <div className="empty-state">
        <span>◉</span>
        <h2>{error ?? "This article could not be found."}</h2>
        <p>It may have been unpublished or the link is incorrect.</p>
        <Link className="btn" to="/articles">
          back to library
        </Link>
      </div>
    );
  }

  const mapped = mapArticle(article);
  const rank =
    [...articles]
      .sort((a, b) => b.votes - a.votes)
      .findIndex((a) => a.id === mapped.id) + 1;
  const windowState =
    liveResult?.voting_status ??
    votingStatus?.status ??
    (mapped.voting === "open" ? "open" : "closed");
  const hasVoted = Boolean(liveResult?.has_voted) || voted.includes(mapped.id);
  const voteCount = liveResult?.vote_count ?? mapped.votes;
  const displayRank = liveResult?.rank ?? (rank > 0 ? rank : null);

  const vote = async () => {
    if (role === "guest") {
      requestVote(mapped.id);
      return;
    }

    setSubmittingVote(true);
    setVoteError(null);

    try {
      const outcome = await castVote(mapped.id);
      if (outcome.ok) {
        setLiveResult((current) => ({
          article_id: mapped.id,
          slug: mapped.slug,
          title: mapped.title,
          vote_count: outcome.voteCount,
          rank: current?.rank ?? displayRank,
          has_voted: true,
          voting_status: windowState,
          updated_at: new Date().toISOString(),
        }));
        setConfirm(false);
        setSuccess(true);
        window.setTimeout(() => setSuccess(false), 3000);
      } else {
        setVoteError(outcome.message);
        if (
          outcome.code === "vote_already_cast" ||
          outcome.code === "voting_period_inactive"
        ) {
          setConfirm(false);
        }
      }
    } finally {
      setSubmittingVote(false);
    }
  };

  const paragraphs = (article.body || "")
    .split(/\n{2,}/)
    .map((block) => block.trim())
    .filter(Boolean);
  return (
    <article className="article-detail">
      <div className="article-crumb">
        <button onClick={() => navigate(-1)}>
          <ArrowLeft size={15} /> back to library
        </button>
        <span>{mapped.category}</span>
      </div>
      <header>
        <span className="eyebrow">field note · {mapped.date}</span>
        <h1>{mapped.title}</h1>
        <div className="byline">
          <span>
            Written by <strong>{mapped.author}</strong>
          </span>
          <span>{mapped.faculty}</span>
          {mapped.is_featured && <span>featured</span>}
        </div>
      </header>
      <figure className="cover">
        <img src={mapped.image} alt="Scientific research connected to the article" />
        <figcaption>MINERVA editorial series · 2025</figcaption>
      </figure>
      <div className="article-layout">
        <div className="prose">
          <p className="lead">{mapped.excerpt}</p>
          {paragraphs.map((block, index) =>
            index === 0 ? (
              <p key={index}>{block}</p>
            ) : (
              <p key={index}>{block}</p>
            ),
          )}
          {article.attachments && article.attachments.length > 0 && (
            <div className="documents">
              <h3>Research Attachments &amp; Downloads</h3>
              {article.attachments.map((attachment) => (
                <a
                  className="attachment-row"
                  key={attachment.id}
                  href={
                    attachment.download_url ||
                    getAttachmentDownloadUrl(article.id, attachment.id)
                  }
                  download={attachment.original_name}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <Download />
                  <span>
                    <strong>{attachment.original_name}</strong>
                    <small>
                      {attachment.kind} · {attachment.mime_type} ·{" "}
                      {formatAttachmentSize(attachment.size_bytes)}
                    </small>
                  </span>
                </a>
              ))}
            </div>
          )}
        </div>
        <aside className="vote-panel">
          <span className="eyebrow">the public ballot.</span>
          {role === "guest" ? (
            <div className="vote-count">
              <strong>—</strong>
              <span>
                members ballot
                <br />
                sign in to view live tally
              </span>
            </div>
          ) : (
            <div className="vote-count">
              <strong>{voteCount}</strong>
              <span>
                votes cast
                <br />
                rank #{displayRank ?? "—"}
              </span>
            </div>
          )}
          <div className={`status-line ${windowState === "open" ? "open" : "closed"}`}>
            {windowState === "open"
              ? "voting is open"
              : windowState === "upcoming"
                ? "voting opens soon"
                : "voting has closed"}
          </div>
          {votingStatus?.period && (
            <p>
              {votingStatus.period.name} ·{" "}
              {new Date(votingStatus.period.starts_at).toLocaleDateString("en-US", {
                day: "2-digit",
                month: "short",
              })}{" "}
              –{" "}
              {new Date(votingStatus.period.ends_at).toLocaleDateString("en-US", {
                day: "2-digit",
                month: "short",
                year: "numeric",
              })}
            </p>
          )}
          {voteError && (
            <p className="field-error" role="alert">
              {voteError}
            </p>
          )}
          {hasVoted ? (
            <div className="recorded">
              <Medal />
              <strong>vote recorded</strong>
              <small>Your choice is sealed.</small>
            </div>
          ) : windowState !== "open" ? (
            <button className="btn full" disabled>
              {windowState === "upcoming" ? "voting opens soon" : "voting closed"}
            </button>
          ) : (
            <button
              className="btn full"
              onClick={() => (role === "guest" ? void vote() : setConfirm(true))}
            >
              {role === "guest" ? "sign in to vote" : "cast your vote"} <ArrowRight size={16} />
            </button>
          )}
          <p>One vote per participant. Votes are permanent and publicly counted.</p>
        </aside>
      </div>
      <CommentSection articleId={article.id} />
      <section className="related">
        <span className="eyebrow">continue reading.</span>
        <div>
          {articles
            .filter((a) => a.id !== mapped.id && a.status === "published")
            .slice(0, 3)
            .map((a, i) => (
              <ArticleCard article={a} key={a.id} index={i + 1} compact />
            ))}
        </div>
      </section>
      {confirm && (
        <div className="modal-backdrop">
          <div className="confirm-modal">
            <button
              className="modal-close"
              disabled={submittingVote}
              onClick={() => setConfirm(false)}
            >
              <X />
            </button>
            <span className="eyebrow">your ballot.</span>
            <h2>Make this choice count?</h2>
            <p>
              You are voting for “{mapped.title}”. Votes are final and cannot be changed,
              withdrawn, or resubmitted.
            </p>
            {voteError && (
              <p className="field-error" role="alert">
                {voteError}
              </p>
            )}
            <div>
              <button
                className="btn outline"
                disabled={submittingVote}
                onClick={() => setConfirm(false)}
              >
                not yet
              </button>
              <button className="btn" disabled={submittingVote} onClick={() => void vote()}>
                {submittingVote ? "sealing…" : "seal my vote"}
              </button>
            </div>
          </div>
        </div>
      )}
      {success && (
        <div className="vote-toast" role="status" aria-live="polite">
          <Medal />
          <div>
            <strong>Vote recorded</strong>
            <span>Your voice is now part of the count.</span>
          </div>
        </div>
      )}
    </article>
  );
}

export function Leaderboard() {
  const { role, votingStatus } = useApp();
  const [entries, setEntries] = useState<LeaderboardEntryDto[]>([]);
  const [updatedAt, setUpdatedAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const retry = useCallback(() => {
    setReloadToken((current) => current + 1);
  }, []);

  useEffect(() => {
    if (role === "guest") {
      setEntries([]);
      setUpdatedAt(null);
      setError(null);
      setLoading(false);
      return;
    }

    const controller = new AbortController();
    setLoading(true);
    setError(null);

    const loadBoard = (isInitial: boolean) => {
      getLeaderboard(controller.signal)
        .then((response) => {
          if (controller.signal.aborted) return;
          setEntries(response.entries);
          setUpdatedAt(response.updated_at ?? null);
          setError(null);
        })
        .catch((requestError) => {
          if (controller.signal.aborted || !isInitial) return;
          setError(
            requestError instanceof ApiUnavailableError
              ? "The MINERVA API is unreachable right now."
              : requestError instanceof ApiError
                ? requestError.message
                : "Unable to load live standings.",
          );
        })
        .finally(() => {
          if (isInitial && !controller.signal.aborted) setLoading(false);
        });
    };

    loadBoard(true);

    const interval = votingStatus?.is_open
      ? window.setInterval(() => loadBoard(false), 20000)
      : undefined;

    // Real-time WebSocket listener via Laravel Reverb: instant standings update
    const periodId = votingStatus?.period?.id;
    const unsubscribe = periodId
      ? subscribeVotingResults(periodId, (payload) => {
          setEntries((prev) => {
            const updated = prev.map((item) =>
              item.article_id === payload.article_id
                ? { ...item, vote_count: payload.vote_count }
                : item,
            );
            return updated.sort((a, b) => b.vote_count - a.vote_count);
          });
          setUpdatedAt(payload.updated_at);
        })
      : () => {};

    return () => {
      controller.abort();
      if (interval !== undefined) window.clearInterval(interval);
      unsubscribe();
    };
  }, [role, votingStatus?.is_open, votingStatus?.period?.id, reloadToken]);

  if (role === "guest") {
    return (
      <div className="page leaderboard-page">
        <header className="editorial-header">
          <span className="eyebrow">members-only live count</span>
          <h1>the standing.</h1>
          <p>
            Every voice, counted in public. Sign in as a registered participant to view live
            standings and vote tallies.
          </p>
        </header>
        <div className="empty-state">
          <span>◉</span>
          <h2>live results require sign-in.</h2>
          <p>Only registered participants can inspect live voting standings.</p>
          <Link className="btn" to="/login">
            sign in to view standings
          </Link>
        </div>
      </div>
    );
  }

  const max = entries[0]?.vote_count || 1;
  const refreshedLabel = updatedAt
    ? new Date(updatedAt).toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" })
    : "just now";

  return (
    <div className="page leaderboard-page">
      <header className="editorial-header">
        <span className="eyebrow">live public count · refreshed {refreshedLabel}</span>
        <h1>the standing.</h1>
        <p>
          Every voice, counted in public. The leading article will receive MINERVA’s Most Popular
          Article award.
        </p>
      </header>
      {loading ? (
        <div className="empty-state">
          <span>◉</span>
          <h2>counting the ballots.</h2>
          <p>Loading live article standings.</p>
        </div>
      ) : error ? (
        <div className="empty-state">
          <span>◉</span>
          <h2>standings unavailable.</h2>
          <p>{error}</p>
          <button onClick={retry}>retry</button>
        </div>
      ) : entries.length === 0 ? (
        <div className="empty-state">
          <span>◉</span>
          <h2>no published articles yet.</h2>
          <p>Standings will appear once essays are published.</p>
        </div>
      ) : (
        <div className="leader-table">
          <div className="leader-table-head">
            <span>Rank / Article</span>
            <span>Category</span>
            <span>Public votes</span>
          </div>
          {entries.map((entry, i) => (
            <Link
              to={`/articles/${entry.slug || entry.article_id}`}
              key={entry.article_id}
              className={i < 3 ? `medal medal-${i + 1}` : ""}
            >
              <strong>{String(entry.rank).padStart(2, "0")}</strong>
              <div>
                <h3>{entry.title}</h3>
                <small>
                  {entry.author?.name ?? "Unknown author"}
                  {entry.has_voted ? " · voted" : ""}
                </small>
                <i style={{ width: `${max ? (entry.vote_count / max) * 100 : 0}%` }} />
              </div>
              <span>{entry.category?.name ?? "Uncategorized"}</span>
              <b>{entry.vote_count}</b>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

export function About() {
  return (
    <div className="about-page">
      <header className="about-hero">
        <div>
          <span className="eyebrow">our story.</span>
          <h1>
            Clarity is a form
            <br />
            of <em>generosity.</em>
          </h1>
        </div>
        <p>
          A year-round IEEE EMBS USJ Chapter initiative empowering students from diverse academic
          backgrounds to develop science communication and writing skills.
        </p>
      </header>
      <section className="manifesto">
        <span aria-hidden="true">"</span>
        <p>
          We believe scientific literacy grows not from simplifying difficult ideas, but from
          explaining them with care.
        </p>
      </section>
      <section className="pillars">
        <div className="section-title">
          <span className="eyebrow">three pillars.</span>
          <h2>
            A living programme,
            <br />
            not only a prize.
          </h2>
        </div>
        {[
          [
            "01",
            "Workshops",
            "Discipline-focused sessions in scientific writing, science communication and content development, led by academics and experienced writers.",
          ],
          [
            "02",
            "Article series",
            "Weekly, academically reviewed undergraduate articles, including an inter-university Open Category and recognition through Most Popular and Best Article awards.",
          ],
          [
            "03",
            "E-magazine",
            "A biannual record of the questions, breakthroughs and voices shaping our community.",
          ],
        ].map(([n, t, b]) => (
          <div className="pillar" key={n}>
            <strong>{n}</strong>
            <h3>{t}</h3>
            <p>{b}</p>
          </div>
        ))}
      </section>
      <section className="numbers">
        <div>
          <strong>—</strong>
          <span>student participants</span>
        </div>
        <div>
          <strong>—</strong>
          <span>countries represented by EMBS</span>
        </div>
        <div>
          <strong>—</strong>
          <span>EMBS members worldwide</span>
        </div>
      </section>
      <section className="about-timeline">
        <span className="eyebrow">project timeline.</span>
        {[
          "June · First workshop",
          "August · Theme-wise article publishing",
          "October · Inter-university Open Category begins",
          "November–December · Research and scientific writing workshop series",
          "December · Awarding ceremony",
        ].map((item, i) => (
          <div key={item}>
            <span>0{i + 1}</span>
            <h3>{item}</h3>
          </div>
        ))}
      </section>
    </div>
  );
}

export function NotFound() {
  return (
    <div className="not-found">
      <span>404</span>
      <h1>
        even Athena loses
        <br />
        her way sometimes.
      </h1>
      <p>This page has wandered beyond the map.</p>
      <Link className="btn" to="/">
        return home
      </Link>
    </div>
  );
}
