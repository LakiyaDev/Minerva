export type Article = {
  id: string;
  title: string;
  author: string;
  faculty: string;
  category: string;
  date: string;
  votes: number;
  status: "published" | "draft" | "unpublished";
  voting: "open" | "closed";
  image: string;
  excerpt: string;
};

export const initialArticles: Article[] = [];

export const participants: Array<
  [string, string, string, number, "active" | "inactive"]
> = [];

export const activity: Array<{ day: string; votes: number }> = [];
