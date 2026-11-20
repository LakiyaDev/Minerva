import { useCallback, useEffect, useState, type FormEvent } from "react";
import { MessageSquare, Send, Trash2 } from "lucide-react";
import { Link } from "react-router";
import { ApiError } from "../services/apiClient";
import {
  deleteComment,
  getArticleComments,
  postComment,
  type CommentDto,
} from "../services/commentService";
import { useApp } from "../store";

function getInitials(name?: string): string {
  if (!name) return "?";
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0] ?? ""}${parts[parts.length - 1][0] ?? ""}`.toUpperCase();
}

function formatCommentDate(iso?: string): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleString("en-US", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function CommentSection({ articleId }: { articleId: string }) {
  const { role, user } = useApp();
  const [comments, setComments] = useState<CommentDto[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [body, setBody] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  const loadComments = useCallback(
    async (targetPage: number, append = false, signal?: AbortSignal) => {
      if (!append) setLoading(true);
      setError(null);

      try {
        const res = await getArticleComments(articleId, targetPage, signal);
        if (signal?.aborted) return;
        setComments((prev) => (append ? [...prev, ...res.data] : res.data));
        setTotal(res.meta?.total ?? res.data.length);
        setPage(res.meta?.current_page ?? targetPage);
        setLastPage(res.meta?.last_page ?? 1);
      } catch (err) {
        if (signal?.aborted) return;
        setError(err instanceof Error ? err.message : "Unable to load comments.");
      } finally {
        if (!signal?.aborted) setLoading(false);
      }
    },
    [articleId],
  );

  useEffect(() => {
    const controller = new AbortController();
    void loadComments(1, false, controller.signal);
    return () => controller.abort();
  }, [loadComments]);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const trimmed = body.trim();
    if (trimmed.length < 2) {
      setSubmitError("Comment must be at least 2 characters.");
      return;
    }

    setSubmitting(true);
    setSubmitError(null);

    try {
      const created = await postComment(articleId, trimmed);
      setComments((prev) => [...prev, created]);
      setTotal((prev) => prev + 1);
      setBody("");
    } catch (err) {
      if (err instanceof ApiError) {
        setSubmitError(err.fieldError("body") || err.message);
      } else {
        setSubmitError(err instanceof Error ? err.message : "Unable to post comment.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (commentId: string) => {
    if (!window.confirm("Delete this comment?")) return;

    setDeletingId(commentId);
    setError(null);

    try {
      await deleteComment(commentId);
      setComments((prev) => prev.filter((item) => item.id !== commentId));
      setTotal((prev) => Math.max(0, prev - 1));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to delete comment.");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <section className="comments-section" style={{ marginTop: "3rem", maxWidth: "780px" }}>
      <header style={{ display: "flex", alignItems: "center", gap: "0.6rem", marginBottom: "1.25rem" }}>
        <MessageSquare size={18} />
        <h2 style={{ margin: 0 }}>
          Discussion <small style={{ opacity: 0.65, fontWeight: 400 }}>({total})</small>
        </h2>
      </header>

      {role === "guest" ? (
        <div
          style={{
            padding: "1rem 1.25rem",
            border: "1px solid rgba(128, 128, 128, 0.25)",
            borderRadius: "8px",
            marginBottom: "1.5rem",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "1rem",
            flexWrap: "wrap",
          }}
        >
          <span>Sign in to join the discussion.</span>
          <Link className="btn outline" to="/login">
            sign in
          </Link>
        </div>
      ) : (
        <form onSubmit={(e) => void handleSubmit(e)} style={{ marginBottom: "1.75rem" }}>
          {submitError && (
            <div className="error-banner" role="alert" style={{ marginBottom: "0.75rem" }}>
              <span>{submitError}</span>
            </div>
          )}
          <label style={{ display: "grid", gap: "0.5rem" }}>
            <span>Add a comment</span>
            <textarea
              rows={3}
              required
              minLength={2}
              maxLength={2000}
              disabled={submitting}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="Share your perspective on this article…"
            />
          </label>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: "0.6rem" }}>
            <small className="field-hint">{body.trim().length} / 2000</small>
            <button className="btn" type="submit" disabled={submitting}>
              <Send size={14} /> {submitting ? "posting…" : "post comment"}
            </button>
          </div>
        </form>
      )}

      {error && (
        <div className="error-banner" role="alert" style={{ marginBottom: "1rem" }}>
          <span>{error}</span>
        </div>
      )}

      {loading ? (
        <p className="empty-state-copy">Loading discussion…</p>
      ) : comments.length === 0 ? (
        <p className="empty-state-copy">No comments yet. Start the conversation.</p>
      ) : (
        <div style={{ display: "grid", gap: "1rem" }}>
          {comments.map((comment) => {
            const isOwner = Boolean(user?.id && comment.author?.id === user.id);

            return (
              <article
                key={comment.id}
                style={{
                  padding: "1rem 1.15rem",
                  border: "1px solid rgba(128, 128, 128, 0.2)",
                  borderRadius: "8px",
                  display: "grid",
                  gap: "0.5rem",
                }}
              >
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "0.75rem" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.65rem" }}>
                    <span
                      style={{
                        width: "30px",
                        height: "30px",
                        borderRadius: "50%",
                        display: "inline-flex",
                        alignItems: "center",
                        justifyContent: "center",
                        fontSize: "0.75rem",
                        fontWeight: 600,
                        background: "rgba(128, 128, 128, 0.16)",
                      }}
                    >
                      {getInitials(comment.author?.name)}
                    </span>
                    <div>
                      <strong>{comment.author?.name ?? "Reader"}</strong>
                      {comment.created_at && (
                        <small style={{ display: "block", opacity: 0.65 }}>
                          {formatCommentDate(comment.created_at)}
                        </small>
                      )}
                    </div>
                  </div>
                  {isOwner && !comment.is_removed && (
                    <button
                      type="button"
                      className="btn outline"
                      disabled={deletingId === comment.id}
                      onClick={() => void handleDelete(comment.id)}
                    >
                      <Trash2 size={14} /> {deletingId === comment.id ? "deleting…" : "delete"}
                    </button>
                  )}
                </div>
                {comment.is_removed ? (
                  <p style={{ margin: 0, fontStyle: "italic", opacity: 0.7 }}>{comment.body}</p>
                ) : (
                  <p style={{ margin: 0, whiteSpace: "pre-wrap" }}>{comment.body}</p>
                )}
              </article>
            );
          })}
          {page < lastPage && (
            <button
              type="button"
              className="btn outline"
              onClick={() => void loadComments(page + 1, true)}
            >
              load more comments
            </button>
          )}
        </div>
      )}
    </section>
  );
}
