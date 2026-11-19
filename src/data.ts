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

const images = [
  "https://images.unsplash.com/photo-1631816285219-c6af3851e360?auto=format&fit=crop&w=1400&q=85",
  "https://images.unsplash.com/photo-1771774982253-adcc7715b8f6?auto=format&fit=crop&w=1400&q=85",
  "https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?auto=format&fit=crop&w=1400&q=85",
  "https://images.unsplash.com/photo-1576086213369-97a306d36557?auto=format&fit=crop&w=1400&q=85",
  "https://images.unsplash.com/photo-1559757148-5c350d0d3c56?auto=format&fit=crop&w=1400&q=85",
  "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1400&q=85",
];

export const initialArticles: Article[] = [
  {
    id: "bench-to-bedside",
    title: "From Bench to Bedside: How Machine Learning Is Reshaping Early Cancer Detection",
    author: "Anuki Perera",
    faculty: "Faculty of Medical Sciences",
    category: "AI in Medicine",
    date: "12 Aug 2025",
    votes: 286,
    status: "published",
    voting: "open",
    image: images[0],
    excerpt:
      "A student-led examination of the algorithms helping clinicians find disease earlier, and the human judgement they must never replace.",
  },
  {
    id: "crispr-ethics",
    title: "CRISPR and the Ethics of Germline Editing: A Student Perspective",
    author: "Nethmi Jayasinghe",
    faculty: "Faculty of Applied Sciences",
    category: "Bioinformatics",
    date: "18 Aug 2025",
    votes: 241,
    status: "published",
    voting: "open",
    image: images[1],
    excerpt:
      "When the power to rewrite inheritance leaves the laboratory, who decides where possibility should end?",
  },
  {
    id: "gentle-sequence",
    title: "Bioinformatics for Beginners: A Gentle Introduction to Sequence Analysis",
    author: "Ravindu Silva",
    faculty: "Faculty of Technology",
    category: "Bioinformatics",
    date: "24 Aug 2025",
    votes: 198,
    status: "published",
    voting: "open",
    image: images[2],
    excerpt:
      "A clear map through sequence alignment, databases and the computational language of living systems.",
  },
  {
    id: "quiet-signal",
    title: "The Quiet Signal: Wearables and the Future of Preventive Care",
    author: "Hiruni Fernando",
    faculty: "Faculty of Medical Sciences",
    category: "Medical Devices",
    date: "01 Sep 2025",
    votes: 175,
    status: "published",
    voting: "open",
    image: images[4],
    excerpt: "What happens when the watch on your wrist begins to notice illness before you do?",
  },
  {
    id: "soil-speaks",
    title: "When Soil Speaks: Sensors, Data and a Smarter Harvest",
    author: "Kavishka Dissanayake",
    faculty: "Faculty of Technology",
    category: "Smart Agriculture",
    date: "07 Sep 2025",
    votes: 157,
    status: "published",
    voting: "closed",
    image: images[5],
    excerpt:
      "Low-cost field sensors are bringing precision agriculture within reach of Sri Lankan smallholders.",
  },
  {
    id: "hidden-cost",
    title: "The Hidden Carbon Cost of Digital Health",
    author: "M. A. Fathima",
    faculty: "Other University",
    category: "Sustainability",
    date: "15 Sep 2025",
    votes: 149,
    status: "published",
    voting: "open",
    image: images[3],
    excerpt:
      "Cloud diagnostics promise access and speed, but their energy footprint deserves a place in the conversation.",
  },
  {
    id: "human-firewall",
    title: "The Human Firewall: Rethinking Cybersecurity in Connected Hospitals",
    author: "Dineth Wijeratne",
    faculty: "Faculty of Computing",
    category: "Cybersecurity",
    date: "22 Sep 2025",
    votes: 134,
    status: "published",
    voting: "open",
    image: images[1],
    excerpt: "Connected care is only as resilient as the people and practices protecting it.",
  },
  {
    id: "teaching-wonder",
    title: "Teaching Wonder: Science Communication Beyond the Lecture Hall",
    author: "Sajini Amarasekara",
    faculty: "Faculty of Humanities",
    category: "Science Communication",
    date: "29 Sep 2025",
    votes: 118,
    status: "published",
    voting: "closed",
    image: images[2],
    excerpt:
      "Why curiosity, narrative and public trust belong at the heart of scientific education.",
  },
  {
    id: "prosthetic-touch",
    title: "A Sense of Touch: The New Language of Bionic Prosthetics",
    author: "Tharuka Senanayake",
    faculty: "Faculty of Engineering",
    category: "Medical Devices",
    date: "05 Oct 2025",
    votes: 97,
    status: "published",
    voting: "open",
    image: images[4],
    excerpt: "Neural interfaces are closing the loop between intention, movement and sensation.",
  },
  {
    id: "diagnostic-bias",
    title: "Who Does the Model Miss? Bias in Diagnostic AI",
    author: "Shenali Peiris",
    faculty: "Faculty of Medical Sciences",
    category: "AI in Medicine",
    date: "11 Oct 2025",
    votes: 82,
    status: "published",
    voting: "open",
    image: images[0],
    excerpt: "Fair health technology begins by asking who is absent from the dataset.",
  },
  {
    id: "ocean-medicine",
    title: "Blue Pharmacies: Medicines Waiting Beneath the Indian Ocean",
    author: "Ishara Gunasekara",
    faculty: "Faculty of Applied Sciences",
    category: "Healthcare Technology",
    date: "20 Oct 2025",
    votes: 0,
    status: "draft",
    voting: "closed",
    image: images[3],
    excerpt:
      "Sri Lanka’s marine biodiversity may hold molecular answers to urgent therapeutic questions.",
  },
  {
    id: "cellular-maps",
    title: "Cellular Maps: Making Spatial Biology Legible",
    author: "Oshini Karunaratne",
    faculty: "Other University",
    category: "Bioinformatics",
    date: "27 Oct 2025",
    votes: 0,
    status: "unpublished",
    voting: "closed",
    image: images[5],
    excerpt:
      "New tools are showing not only which cells exist, but where they gather and why that matters.",
  },
];

export const participants = [
  ["Anuki Perera", "anuki@sjp.ac.lk", "Medical Sciences", 8, "active"],
  ["Ravindu Silva", "ravindu@sjp.ac.lk", "Technology", 6, "active"],
  ["Nethmi Jayasinghe", "nethmi@sjp.ac.lk", "Applied Sciences", 9, "active"],
  ["M. A. Fathima", "fathima@uom.lk", "Other University", 5, "active"],
  ["Sajini Amarasekara", "sajini@sjp.ac.lk", "Humanities", 7, "active"],
  ["Dineth Wijeratne", "dineth@sjp.ac.lk", "Computing", 4, "active"],
  ["Hiruni Fernando", "hiruni@sjp.ac.lk", "Medical Sciences", 8, "active"],
  ["Kavishka Dissanayake", "kavishka@sjp.ac.lk", "Technology", 3, "inactive"],
];

export const activity = [
  { day: "01 Nov", votes: 38 },
  { day: "08 Nov", votes: 64 },
  { day: "15 Nov", votes: 91 },
  { day: "22 Nov", votes: 143 },
  { day: "29 Nov", votes: 187 },
  { day: "06 Dec", votes: 244 },
];
