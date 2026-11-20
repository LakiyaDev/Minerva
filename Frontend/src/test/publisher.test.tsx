import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { createMemoryRouter, RouterProvider } from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PublisherDashboard } from "../pages/Publisher";

const mockLoadPublisherArticles = vi.fn();
const mockPublishOwn = vi.fn();
const mockUseApp = vi.fn();

vi.mock("../store", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../store")>();
  return {
    ...actual,
    useApp: () => mockUseApp(),
  };
});

describe("Publisher Direct Publishing Workflow (FE-42, FE-71)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockLoadPublisherArticles.mockResolvedValue([
      {
        id: "art-draft",
        slug: "draft-telemetry",
        title: "Draft Telemetry Study",
        author: "Dr. Sam",
        category: "Bioinformatics",
        date: "20 Nov",
        abstract: "Draft abstract",
        excerpt: "Draft abstract",
        votes: 0,
        status: "draft",
        voting: "open",
        image: "",
      },
      {
        id: "art-pub",
        slug: "published-ai",
        title: "Published Clinical AI",
        author: "Dr. Sam",
        category: "AI",
        date: "19 Nov",
        abstract: "Published abstract",
        excerpt: "Published abstract",
        votes: 12,
        status: "published",
        voting: "open",
        image: "",
      },
    ]);

    mockUseApp.mockReturnValue({
      loadPublisherArticles: mockLoadPublisherArticles,
      publishOwn: mockPublishOwn,
    });
  });

  function renderDashboard() {
    const router = createMemoryRouter(
      [
        { path: "/publisher", Component: PublisherDashboard },
        { path: "/publisher/create", element: <div>Create</div> },
        { path: "/publisher/edit/:id", element: <div>Edit</div> },
        { path: "/articles/:id", element: <div>Public Article</div> },
      ],
      { initialEntries: ["/publisher"] },
    );
    return render(<RouterProvider router={router} />);
  }

  it("renders direct publish on drafts and has no review queue or submit-for-review controls", async () => {
    mockPublishOwn.mockResolvedValueOnce({ ok: true });
    const user = userEvent.setup();

    renderDashboard();

    expect(await screen.findByText("Draft Telemetry Study")).toBeInTheDocument();
    expect(screen.getByText("Published Clinical AI")).toBeInTheDocument();

    // Ensure no review-queue artifacts exist
    expect(screen.queryByRole("button", { name: /submit for review/i })).not.toBeInTheDocument();
    expect(screen.queryByText(/pending review/i)).not.toBeInTheDocument();

    const publishBtn = screen.getByRole("button", { name: /publish article/i });
    await user.click(publishBtn);

    await waitFor(() => {
      expect(mockPublishOwn).toHaveBeenCalledWith("art-draft");
    });

    expect(await screen.findByText("Article published.")).toBeInTheDocument();
  });
});
