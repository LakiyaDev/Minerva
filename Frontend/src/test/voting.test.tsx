import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { createMemoryRouter, RouterProvider } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ArticleDetail } from "../pages/PublicPages";

const mockGetArticle = vi.fn();
const mockGetArticleVoteResults = vi.fn();
const mockCastVote = vi.fn();
const mockRequestVote = vi.fn();
const mockUseApp = vi.fn();

vi.mock("../services/articleService", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../services/articleService")>();
  return {
    ...actual,
    getArticle: (...args: unknown[]) => mockGetArticle(...args),
  };
});

vi.mock("../services/votingService", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../services/votingService")>();
  return {
    ...actual,
    getArticleVoteResults: (...args: unknown[]) => mockGetArticleVoteResults(...args),
  };
});

vi.mock("../services/echoService", () => ({
  subscribeVotingResults: vi.fn(() => () => {}),
  disconnectEcho: vi.fn(),
}));

vi.mock("../components/CommentSection", () => ({
  CommentSection: () => <div data-testid="comments-section" />,
}));

vi.mock("../store", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../store")>();
  return {
    ...actual,
    useApp: () => mockUseApp(),
  };
});

const sampleArticle = {
  id: "art-1",
  slug: "neural-telemetry",
  title: "Neural Telemetry in Clinical Trials",
  abstract: "Summary of telemetry approaches.",
  body: "Full body paragraph one.\n\nFull body paragraph two.",
  category: { id: "cat-1", name: "Bioinformatics", slug: "bioinformatics" },
  author: { id: "auth-1", name: "Dr. Elena" },
  status: "published" as const,
  is_featured: true,
  votes_count: 14,
  published_at: "2026-11-01T10:00:00Z",
  attachments: [],
};

describe("Voting Confirmation & Live Ballot (FE-31, FE-32, FE-71)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArticle.mockResolvedValue(sampleArticle);
    mockGetArticleVoteResults.mockResolvedValue({
      article_id: "art-1",
      slug: "neural-telemetry",
      title: "Neural Telemetry in Clinical Trials",
      vote_count: 14,
      rank: 2,
      has_voted: false,
      voting_status: "open",
      updated_at: "2026-11-20T10:00:00Z",
    });
  });

  function renderArticleDetail() {
    const router = createMemoryRouter(
      [{ path: "/articles/:id", Component: ArticleDetail }],
      { initialEntries: ["/articles/neural-telemetry"] },
    );
    return render(<RouterProvider router={router} />);
  }

  it("prompts guests to sign in before voting and hides live tally", async () => {
    const user = userEvent.setup();
    mockUseApp.mockReturnValue({
      role: "guest",
      voted: [],
      articles: [],
      votingStatus: {
        is_open: true,
        status: "open",
        server_time: "2026-11-20T10:00:00Z",
        period: { id: "per-1", name: "Annual 2026", starts_at: "2026-11-01", ends_at: "2026-11-30" },
      },
      castVote: mockCastVote,
      requestVote: mockRequestVote,
    });

    renderArticleDetail();

    const signInVoteBtn = await screen.findByRole("button", { name: /sign in to vote/i });
    expect(screen.getByText(/sign in to view live tally/i)).toBeInTheDocument();

    await user.click(signInVoteBtn);
    expect(mockRequestVote).toHaveBeenCalledWith("art-1");
  });

  it("requires final-vote confirmation before casting a participant vote", async () => {
    const user = userEvent.setup();
    mockCastVote.mockResolvedValueOnce({ ok: true, voteCount: 15 });
    mockUseApp.mockReturnValue({
      role: "participant",
      voted: [],
      articles: [],
      votingStatus: {
        is_open: true,
        status: "open",
        server_time: "2026-11-20T10:00:00Z",
        period: { id: "per-1", name: "Annual 2026", starts_at: "2026-11-01", ends_at: "2026-11-30" },
      },
      castVote: mockCastVote,
      requestVote: mockRequestVote,
    });

    renderArticleDetail();

    const castBtn = await screen.findByRole("button", { name: /cast your vote/i });
    await user.click(castBtn);

    expect(screen.getByText(/Votes are final and cannot be changed/i)).toBeInTheDocument();
    expect(mockCastVote).not.toHaveBeenCalled();

    const confirmBtn = screen.getByRole("button", { name: /seal my vote/i });
    await user.click(confirmBtn);

    await waitFor(() => {
      expect(mockCastVote).toHaveBeenCalledWith("art-1");
    });

    expect(await screen.findByText(/Your choice is sealed\./i)).toBeInTheDocument();
  });
});
