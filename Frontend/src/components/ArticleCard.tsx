import { ArrowUpRight, Check, Feather } from "lucide-react";
import { Link } from "react-router";
import type { Article } from "../data";
import { useApp } from "../store";

export function ArticleCard({
  article,
  index = 1,
  compact = false,
}: {
  article: Article;
  index?: number;
  compact?: boolean;
}) {
  const { role, voted, requestVote } = useApp();
  const hasVoted = voted.includes(article.id);
  const href = `/articles/${article.slug || article.id}`;
  return (
    <article className={`article-card ${compact ? "compact" : ""}`}>
      <Link to={href} className="article-image">
        {article.image ? (
          <img src={article.image} alt="" loading="lazy" />
        ) : (
          <span className="article-placeholder" aria-hidden="true" />
        )}
        <span className="article-index">{String(index).padStart(2, "0")}</span>
        <ArrowUpRight className="card-arrow" />
      </Link>
      <div className="article-meta">
        <span>{article.category}</span>
        <span>{article.date}</span>
      </div>
      <Link to={href}>
        <h3>{article.title}</h3>
      </Link>
      {!compact && <p>{article.excerpt}</p>}
      <div className="article-foot">
        <span>
          <Feather size={14} />
          {article.votes} votes
        </span>
        {hasVoted ? (
          <span className="voted">
            <Check size={14} /> voted
          </span>
        ) : article.voting === "closed" ? (
          <span className="closed">voting closed</span>
        ) : (
          <button onClick={() => (role === "guest" ? requestVote(article.id) : undefined)}>
            {role === "guest" ? "sign in to vote" : "view & vote"} →
          </button>
        )}
      </div>
    </article>
  );
}
