import { createContext, useContext, useMemo, useState, type ReactNode } from "react";
import { initialArticles, type Article } from "./data";

type Role = "guest" | "participant" | "admin";
type Store = {
  role: Role;
  setRole: (role: Role) => void;
  articles: Article[];
  voted: string[];
  authOpen: boolean;
  setAuthOpen: (open: boolean) => void;
  pendingVote: string | null;
  requestVote: (id: string) => void;
  castVote: (id: string) => boolean;
  toggleVoting: (id: string) => void;
  togglePublished: (id: string) => void;
  deleteArticle: (id: string) => void;
  addArticle: (article: Article) => void;
  unread: number;
  markRead: () => void;
};

const AppContext = createContext<Store | null>(null);

export function AppProvider({ children }: { children: ReactNode }) {
  const [role, setRoleState] = useState<Role>(() => {
    const storedRole = localStorage.getItem("minerva-role");
    return storedRole === "participant" || storedRole === "admin" ? storedRole : "guest";
  });
  const [articles, setArticles] = useState(initialArticles);
  const [voted, setVoted] = useState<string[]>([
    "crispr-ethics",
    "gentle-sequence",
    "teaching-wonder",
  ]);
  const [authOpen, setAuthOpen] = useState(false);
  const [pendingVote, setPendingVote] = useState<string | null>(null);
  const [unread, setUnread] = useState(3);

  const setRole = (next: Role) => {
    setRoleState(next);
    localStorage.setItem("minerva-role", next);
    if (next !== "guest" && pendingVote) setAuthOpen(false);
  };
  const requestVote = (id: string) => {
    if (role === "guest") {
      setPendingVote(id);
      setAuthOpen(true);
    }
  };
  const castVote = (id: string) => {
    const article = articles.find((item) => item.id === id);
    if (!article || article.voting !== "open" || voted.includes(id)) return false;
    setVoted((current) => [...current, id]);
    setArticles((current) =>
      current.map((item) => (item.id === id ? { ...item, votes: item.votes + 1 } : item)),
    );
    setPendingVote(null);
    return true;
  };
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

  const value = useMemo(
    () => ({
      role,
      setRole,
      articles,
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
    }),
    [role, articles, voted, authOpen, pendingVote, unread],
  );
  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) throw new Error("useApp must be used inside AppProvider");
  return context;
}
