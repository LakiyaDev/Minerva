import { useCallback, useEffect, useState, type ChangeEvent, type DragEvent, type FormEvent } from "react";
import { ArrowUpRight, Check, Download, Edit3, Eye, Paperclip, Plus, Search, Send, Trash2, Upload, X } from "lucide-react";
import { Link, useLocation, useNavigate, useParams } from "react-router";
import { articleStatusLabels, type Article, type ArticleStatus } from "../data";
import {
  deleteAttachment,
  formatAttachmentSize,
  getAttachmentDownloadUrl,
  getPublisherArticle,
  uploadAttachment,
  type ArticleAttachmentDto,
  type ArticleStatusHistoryDto,
} from "../services/articleService";
import { ApiError, ApiUnavailableError } from "../services/apiClient";
import { useApp, type EditorialResult } from "../store";

function formatTimestamp(value?: string): string {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString("en-US", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function PublisherDashboard() {
  const { loadPublisherArticles, submitForReview, publishOwn, canPublishDirectly } = useApp();
  const [rows, setRows] = useState<Article[]>([]);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<ArticleStatus | "">("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const location = useLocation();

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
      setRows(await loadPublisherArticles());
    } catch (err) {
      setError(
        err instanceof ApiUnavailableError
          ? "The MINERVA API is unreachable."
          : err instanceof ApiError
            ? err.message
            : "Unable to load your articles.",
      );
    } finally {
      setLoading(false);
    }
  }, [loadPublisherArticles]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const runAction = async (
    id: string,
    fn: () => Promise<EditorialResult>,
    successMessage: string,
  ) => {
    setBusyId(id);
    setError(null);
    setNotice(null);
    const result = await fn();
    setBusyId(null);

    if (!result.ok) {
      setError(result.message);
      return;
    }

    setNotice(successMessage);
    await reload();
  };

  const counts = {
    total: rows.length,
    drafts: rows.filter((a) => a.status === "draft").length,
    pending: rows.filter((a) => a.status === "pending_review").length,
    rejected: rows.filter((a) => a.status === "rejected").length,
    published: rows.filter((a) => a.status === "published").length,
  };

  const filtered = rows.filter(
    (a) =>
      (!statusFilter || a.status === statusFilter) &&
      a.title.toLowerCase().includes(search.toLowerCase()),
  );

  return (
    <div className="admin-page">
      <header className="admin-heading">
        <div>
          <span className="eyebrow">publisher studio.</span>
          <h1>My articles.</h1>
        </div>
        <Link className="btn" to="/publisher/create">
          <Plus /> create article
        </Link>
      </header>

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

      <div className="stats-row">
        {[
          [counts.total, "total articles"],
          [counts.drafts, "drafts"],
          [counts.pending, "pending review"],
          [counts.rejected, "needs revision"],
          [counts.published, "published"],
        ].map(([value, label]) => (
          <div className="stat-card" key={label}>
            <strong>{value}</strong>
            <span>{label}</span>
          </div>
        ))}
      </div>

      <div className="table-tools">
        <label>
          <Search />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search your articles"
          />
        </label>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as ArticleStatus | "")}
        >
          <option value="">All statuses</option>
          {(Object.keys(articleStatusLabels) as ArticleStatus[]).map((statusKey) => (
            <option key={statusKey} value={statusKey}>
              {articleStatusLabels[statusKey]}
            </option>
          ))}
        </select>
      </div>

      <div className="data-table article-admin-table">
        <div className="table-head">
          <span>Article</span>
          <span>Category</span>
          <span>Status</span>
          <span>Updated</span>
          <span>Actions</span>
        </div>
        {loading ? (
          <p className="empty-state-copy">Loading your articles…</p>
        ) : filtered.length === 0 ? (
          <p className="empty-state-copy">No articles yet. Create your first draft.</p>
        ) : (
          filtered.map((a) => {
            const editable = a.status === "draft" || a.status === "rejected";
            const canDirectPublish =
              canPublishDirectly && (a.status === "draft" || a.status === "approved");

            return (
              <div className="table-row" key={a.id}>
                <div className="table-article">
                  <span>
                    <strong>{a.title}</strong>
                    <small>{a.slug}</small>
                  </span>
                </div>
                <span>{a.category}</span>
                <span className={`status ${a.status}`}>{articleStatusLabels[a.status]}</span>
                <span>{a.date}</span>
                <div className="row-actions">
                  <Link
                    to={`/publisher/edit/${a.id}`}
                    title={editable ? "Edit draft" : "Inspect status & history"}
                    aria-label={editable ? "Edit draft" : "Inspect status & history"}
                  >
                    <Edit3 />
                  </Link>
                  {editable && (
                    <button
                      type="button"
                      title="Submit for review"
                      aria-label="Submit for review"
                      disabled={busyId === a.id}
                      onClick={() =>
                        void runAction(
                          a.id,
                          () => submitForReview(a.id),
                          "Article submitted for editorial review.",
                        )
                      }
                    >
                      <Send />
                    </button>
                  )}
                  {canDirectPublish && (
                    <button
                      type="button"
                      title="Direct publish"
                      aria-label="Direct publish"
                      disabled={busyId === a.id}
                      onClick={() =>
                        void runAction(a.id, () => publishOwn(a.id), "Article published directly.")
                      }
                    >
                      <ArrowUpRight />
                    </button>
                  )}
                  {a.status === "published" && (
                    <Link
                      to={`/articles/${a.slug || a.id}`}
                      title="View published article"
                      aria-label="View published article"
                    >
                      <Eye />
                    </Link>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}

export function PublisherArticleEditor() {
  const { id } = useParams();
  const isEditing = Boolean(id);
  const navigate = useNavigate();
  const { categories, createDraft, updateDraft, submitForReview, publishOwn, canPublishDirectly } =
    useApp();

  const [title, setTitle] = useState("");
  const [abstract, setAbstract] = useState("");
  const [body, setBody] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [status, setStatus] = useState<ArticleStatus>("draft");
  const [history, setHistory] = useState<ArticleStatusHistoryDto[]>([]);
  const [attachments, setAttachments] = useState<ArticleAttachmentDto[]>([]);
  const [attachmentKind, setAttachmentKind] = useState<string>("manuscript");
  const [uploadingAttachment, setUploadingAttachment] = useState(false);
  const [deletingAttachmentId, setDeletingAttachmentId] = useState<string | null>(null);
  const [attachmentError, setAttachmentError] = useState<string | null>(null);
  const [dragActive, setDragActive] = useState(false);
  const [loading, setLoading] = useState(isEditing);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (!id) return;

    const controller = new AbortController();
    setLoading(true);
    setError(null);

    getPublisherArticle(id, controller.signal)
      .then((dto) => {
        if (controller.signal.aborted) return;
        setTitle(dto.title);
        setAbstract(dto.abstract);
        setBody(dto.body);
        setCategoryId(dto.category?.id ?? "");
        setStatus(dto.status);
        setHistory(dto.status_history ?? []);
        setAttachments(dto.attachments ?? []);
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        setError(err instanceof Error ? err.message : "Unable to load this article.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [id]);

  const isEditable = !isEditing || status === "draft" || status === "rejected";
  const latestRejection = [...history].reverse().find((entry) => entry.to_status === "rejected");

  const handleAttachmentUpload = async (files: FileList | null) => {
    if (!id || !isEditable || !files || files.length === 0) return;

    setAttachmentError(null);
    setUploadingAttachment(true);

    try {
      const uploaded: ArticleAttachmentDto[] = [];
      for (const file of Array.from(files)) {
        const created = await uploadAttachment(id, file, attachmentKind);
        uploaded.push(created);
      }
      setAttachments((prev) => [...prev, ...uploaded]);
    } catch (err) {
      if (err instanceof ApiError) {
        setAttachmentError(err.fieldError("file") || err.message);
      } else {
        setAttachmentError(err instanceof Error ? err.message : "Unable to upload attachment.");
      }
    } finally {
      setUploadingAttachment(false);
    }
  };

  const handleFileInputChange = (e: ChangeEvent<HTMLInputElement>) => {
    void handleAttachmentUpload(e.target.files);
    e.target.value = "";
  };

  const handleDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setDragActive(false);
    void handleAttachmentUpload(e.dataTransfer.files);
  };

  const handleDeleteAttachment = async (attachment: ArticleAttachmentDto) => {
    if (!id || !isEditable) return;
    if (!window.confirm(`Remove "${attachment.original_name}"?`)) return;

    setAttachmentError(null);
    setDeletingAttachmentId(attachment.id);

    try {
      await deleteAttachment(id, attachment.id);
      setAttachments((prev) => prev.filter((item) => item.id !== attachment.id));
    } catch (err) {
      setAttachmentError(err instanceof Error ? err.message : "Unable to delete attachment.");
    } finally {
      setDeletingAttachmentId(null);
    }
  };

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!isEditable) return;

    const nativeEvent = e.nativeEvent as SubmitEvent;
    const submitter = nativeEvent.submitter as HTMLButtonElement | null;
    const intent = submitter?.value || "draft";

    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    let targetId = id ?? "";
    const payload = {
      title: title.trim(),
      abstract: abstract.trim(),
      body: body.trim(),
      category_id: categoryId,
    };

    const saveResult: EditorialResult = isEditing
      ? await updateDraft(targetId, payload)
      : await createDraft(payload);

    if (!saveResult.ok) {
      setError(saveResult.message);
      setFieldErrors(saveResult.fieldErrors ?? {});
      setSubmitting(false);
      return;
    }

    if (!isEditing) {
      targetId = saveResult.id ?? "";
    }

    if (intent === "submit" && targetId) {
      const submitRes = await submitForReview(targetId);
      setSubmitting(false);
      if (!submitRes.ok) {
        navigate("/publisher", {
          state: { notice: `${submitRes.message} Your draft changes were saved.` },
        });
        return;
      }
      navigate("/publisher", { state: { notice: "Article submitted for review." } });
      return;
    }

    if (intent === "publish" && targetId) {
      const publishRes = await publishOwn(targetId);
      setSubmitting(false);
      if (!publishRes.ok) {
        navigate("/publisher", {
          state: { notice: `${publishRes.message} Your article remains saved as a draft.` },
        });
        return;
      }
      navigate("/publisher", { state: { notice: "Article published directly." } });
      return;
    }

    setSubmitting(false);
    navigate("/publisher", {
      state: { notice: isEditing ? "Draft updated." : "Draft created." },
    });
  };

  if (loading) {
    return (
      <div className="admin-page">
        <p className="empty-state-copy">Loading article workspace…</p>
      </div>
    );
  }

  return (
    <div className="admin-page">
      <header className="admin-heading">
        <div>
          <span className="eyebrow">publisher studio.</span>
          <h1>{isEditing ? "Edit article." : "Create a new article."}</h1>
        </div>
        <Link className="btn outline" to="/publisher">
          back to my articles
        </Link>
      </header>

      {isEditing && (
        <div style={{ marginBottom: "1rem", display: "flex", gap: "0.75rem", alignItems: "center" }}>
          <span className={`status ${status}`}>{articleStatusLabels[status]}</span>
          {!isEditable && (
            <small className="field-hint">
              This article is locked for editing while in {articleStatusLabels[status]} status.
            </small>
          )}
        </div>
      )}

      {latestRejection?.reason && (
        <div className="error-banner" role="alert">
          <span>
            <strong>Reviewer feedback:</strong> {latestRejection.reason}
          </span>
        </div>
      )}

      {error && (
        <div className="error-banner" role="alert">
          <span>{error}</span>
        </div>
      )}

      <form className="editor-form" onSubmit={(e) => void handleSubmit(e)}>
        <section>
          <header>
            <span>01</span>
            <div>
              <h2>Article content</h2>
              <p>Prepare your title, abstract, category, and full manuscript.</p>
            </div>
          </header>
          <label>
            Article title
            <input
              required
              disabled={!isEditable || submitting}
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="A clear, compelling title"
            />
            {fieldErrors.title && <small className="field-error">{fieldErrors.title[0]}</small>}
          </label>
          <label>
            Short description / abstract
            <textarea
              required
              rows={3}
              disabled={!isEditable || submitting}
              value={abstract}
              onChange={(e) => setAbstract(e.target.value)}
              placeholder="A concise introduction for cards and search results."
            />
            {fieldErrors.abstract && (
              <small className="field-error">{fieldErrors.abstract[0]}</small>
            )}
          </label>
          <label>
            Category
            <select
              required
              disabled={!isEditable || submitting}
              value={categoryId}
              onChange={(e) => setCategoryId(e.target.value)}
            >
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
          <label>
            Article content
            <textarea
              required
              rows={12}
              disabled={!isEditable || submitting}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="Write or paste the full article here…"
            />
            {fieldErrors.body && <small className="field-error">{fieldErrors.body[0]}</small>}
          </label>
        </section>

        {isEditable && (
          <section>
            <header>
              <span>02</span>
              <div>
                <h2>Workflow actions</h2>
                <p>
                  Save a private draft, submit to the editorial review queue, or publish directly if
                  authorized.
                </p>
              </div>
            </header>
            <div className="publish-actions">
              <button className="btn outline" type="submit" value="draft" disabled={submitting}>
                {submitting ? "saving…" : "save draft"}
              </button>
              <button className="btn" type="submit" value="submit" disabled={submitting}>
                <Send size={14} /> submit for review
              </button>
              {canPublishDirectly && (
                <button className="btn" type="submit" value="publish" disabled={submitting}>
                  <Check size={14} /> direct publish
                </button>
              )}
            </div>
          </section>
        )}
      </form>

      <section className="editor-form" style={{ marginTop: "2rem" }}>
        <section>
          <header>
            <span>03</span>
            <div>
              <h2>Attachments</h2>
              <p>Upload PDF manuscripts, supplementary documents, or figures (PNG, JPEG, WEBP up to 10 MB).</p>
            </div>
          </header>

          {!isEditing ? (
            <p className="field-hint">
              Save your draft first to upload manuscripts, figures, or supplementary files.
            </p>
          ) : (
            <>
              {attachmentError && (
                <div className="error-banner" role="alert">
                  <span>{attachmentError}</span>
                </div>
              )}

              {isEditable && (
                <div style={{ display: "grid", gap: "0.75rem" }}>
                  <label>
                    Attachment kind
                    <select
                      value={attachmentKind}
                      disabled={uploadingAttachment}
                      onChange={(e) => setAttachmentKind(e.target.value)}
                    >
                      <option value="manuscript">Manuscript</option>
                      <option value="supplement">Supplementary Document</option>
                      <option value="figure">Figure</option>
                      <option value="document">Document</option>
                    </select>
                  </label>

                  <div
                    onDragOver={(e) => {
                      e.preventDefault();
                      setDragActive(true);
                    }}
                    onDragLeave={() => setDragActive(false)}
                    onDrop={handleDrop}
                    style={{
                      border: `2px dashed ${dragActive ? "currentColor" : "rgba(128, 128, 128, 0.35)"}`,
                      borderRadius: "8px",
                      padding: "1.25rem",
                      textAlign: "center",
                      opacity: uploadingAttachment ? 0.6 : 1,
                    }}
                  >
                    <Upload size={18} style={{ margin: "0 auto 0.5rem" }} />
                    <p style={{ marginBottom: "0.5rem" }}>
                      {uploadingAttachment
                        ? "Uploading attachment…"
                        : "Drag and drop PDF or image files here, or choose from your device."}
                    </p>
                    <label className="btn outline" style={{ display: "inline-flex", cursor: "pointer" }}>
                      <Paperclip size={14} /> select file
                      <input
                        type="file"
                        accept=".pdf,image/png,image/jpeg,image/webp"
                        multiple
                        disabled={uploadingAttachment}
                        onChange={handleFileInputChange}
                        style={{ display: "none" }}
                      />
                    </label>
                  </div>
                </div>
              )}

              {attachments.length === 0 ? (
                <p className="field-hint">No attachments uploaded yet.</p>
              ) : (
                <div style={{ display: "grid", gap: "0.6rem", marginTop: "0.5rem" }}>
                  {attachments.map((attachment) => (
                    <div
                      key={attachment.id}
                      style={{
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        gap: "0.75rem",
                        padding: "0.65rem 0.85rem",
                        border: "1px solid rgba(128, 128, 128, 0.2)",
                        borderRadius: "6px",
                      }}
                    >
                      <div style={{ minWidth: 0 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", flexWrap: "wrap" }}>
                          <strong>{attachment.original_name}</strong>
                          <span className="status">{attachment.kind}</span>
                        </div>
                        <small className="field-hint">
                          {attachment.mime_type} · {formatAttachmentSize(attachment.size_bytes)}
                        </small>
                      </div>
                      <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", flexShrink: 0 }}>
                        <a
                          className="btn outline"
                          href={
                            attachment.download_url ||
                            getAttachmentDownloadUrl(id ?? "", attachment.id)
                          }
                          download={attachment.original_name}
                          target="_blank"
                          rel="noopener noreferrer"
                        >
                          <Download size={14} /> download
                        </a>
                        {isEditable && (
                          <button
                            className="btn outline"
                            type="button"
                            disabled={deletingAttachmentId === attachment.id}
                            onClick={() => void handleDeleteAttachment(attachment)}
                          >
                            <Trash2 size={14} />{" "}
                            {deletingAttachmentId === attachment.id ? "removing…" : "remove"}
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </section>
      </section>

      {isEditing && history.length > 0 && (
        <section className="admin-activity" style={{ marginTop: "2rem", maxWidth: "900px" }}>
          <header>
            <span className="eyebrow">editorial trail.</span>
            <h2>Status history</h2>
          </header>
          {history.map((entry, idx) => (
            <div key={`${entry.to_status}-${entry.created_at ?? idx}`}>
              <span>{formatTimestamp(entry.created_at)}</span>
              <div>
                <strong>
                  {entry.from_status
                    ? `${articleStatusLabels[entry.from_status]} → ${articleStatusLabels[entry.to_status]}`
                    : articleStatusLabels[entry.to_status]}
                </strong>
                {entry.reason && <p style={{ marginTop: "4px" }}>{entry.reason}</p>}
              </div>
              <i />
            </div>
          ))}
        </section>
      )}
    </div>
  );
}
