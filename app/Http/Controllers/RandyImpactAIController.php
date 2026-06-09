<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RandyImpactAIController extends Controller
{
    private string $groqApiUrl  = 'https://api.groq.com/openai/v1/chat/completions';
    private string $bibleApiUrl = 'https://bible-api.com';
    private string $groqModel   = 'llama-3.3-70b-versatile';

    // ── MAIN AI PAGE ───────────────────────────────────────
    public function index()
    {
        return view('randyimpact.index');
    }

    // ── LIVE SERMON PAGE ──────────────────────────────────
    public function liveSermon()
    {
        return view('randyimpact.live-sermon');
    }

    // ── PROJECTOR DISPLAY PAGE ─────────────────────────────
    public function projector()
    {
        return view('randyimpact.projector');
    }

    // ── FETCH BIBLE VERSE ──────────────────────────────────
    public function getVerse(Request $request)
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:100',
        ]);

        try {
            $reference = urlencode($validated['reference']);
            $response  = Http::get("{$this->bibleApiUrl}/{$reference}?translation=kjv");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success'   => true,
                    'reference' => $data['reference'] ?? $validated['reference'],
                    'text'      => trim($data['text'] ?? 'Verse not found.'),
                    'verses'    => $data['verses'] ?? [],
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Verse not found.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── DETECT VERSES FROM TRANSCRIPT ─────────────────────
    public function detectVerses(Request $request)
    {
        $validated = $request->validate([
            'transcript' => 'required|string|max:5000',
        ]);

        $references = $this->extractBibleReferences($validated['transcript']);

        if (empty($references)) {
            return response()->json(['success' => true, 'references' => [], 'verses' => []]);
        }

        $verses = [];
        foreach ($references as $ref) {
            try {
                $encoded  = urlencode($ref);
                $response = Http::get("{$this->bibleApiUrl}/{$encoded}?translation=kjv");
                if ($response->successful()) {
                    $data     = $response->json();
                    $verses[] = [
                        'reference' => $data['reference'] ?? $ref,
                        'text'      => trim($data['text'] ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json([
            'success'    => true,
            'references' => $references,
            'verses'     => $verses,
        ]);
    }

    // ── GENERATE SERMON NOTES ─────────────────────────────
    public function generateNotes(Request $request)
    {
        $validated = $request->validate([
            'transcript' => 'required|string|max:10000',
            'topic'      => 'nullable|string|max:255',
        ]);

        $topic  = $validated['topic'] ?? 'the sermon';
        $prompt = "You are a church sermon assistant for GraceWorld International.

Based on the following sermon transcript, generate structured sermon notes.

Topic: {$topic}

Transcript:
{$validated['transcript']}

Generate:
1. **Sermon Title** (creative and engaging)
2. **Main Theme** (one sentence)
3. **Key Points** (3-5 bullet points)
4. **Scripture References** (all Bible verses mentioned)
5. **Prayer Points** (3-5 points based on the sermon)
6. **Application** (how members can apply this in daily life)
7. **Closing Thought** (one powerful sentence)

Format the response clearly with these sections.";

        $result = $this->callGroq($prompt, 2000);
        return response()->json($result);
    }

    // ── BIBLE Q&A ─────────────────────────────────────────
    public function askBible(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        $prompt = "You are RandyImpact AI, a Bible assistant for GraceWorld International church.

Answer the following Bible question with relevant scripture references, clear explanation, and practical application.
Be encouraging, doctrinally sound, and easy to understand.

Question: {$validated['question']}";

        $result = $this->callGroq($prompt, 1500);

        if ($result['success']) {
            return response()->json(['success' => true, 'answer' => $result['text']]);
        }

        return response()->json($result);
    }

    // ── GENERATE SERMON SUMMARY ───────────────────────────
    public function generateSummary(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:10000',
        ]);

        $prompt = "Based on these sermon notes, generate a short inspiring summary (150-200 words) suitable for sharing on a church community feed or WhatsApp. Include a key scripture.

Notes:
{$validated['notes']}";

        $result = $this->callGroq($prompt, 500);

        if ($result['success']) {
            return response()->json(['success' => true, 'summary' => $result['text']]);
        }

        return response()->json($result);
    }

    // ── CALL GROQ API ─────────────────────────────────────
    private function callGroq(string $prompt, int $maxTokens = 1000): array
    {
        try {
            $apiKey = config('services.groq.key');

            if (empty($apiKey)) {
                return ['success' => false, 'message' => 'Groq API key not configured.'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post($this->groqApiUrl, [
                'model'       => $this->groqModel,
                'max_tokens'  => $maxTokens,
                'temperature' => 0.7,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You are RandyImpact AI, a helpful Bible and church assistant for GraceWorld International church in Ghana. Always respond with scripture references and practical application.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? 'No response generated.';
                return ['success' => true, 'text' => $text, 'notes' => $text, 'answer' => $text];
            }

            \Log::error('Groq API Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'AI error: ' . $response->status() . ' — ' . $response->body(),
            ];

        } catch (\Exception $e) {
            \Log::error('Groq Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── EXTRACT BIBLE REFERENCES ──────────────────────────
    private function extractBibleReferences(string $text): array
    {
        $books = [
            'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
            'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel',
            '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles',
            'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalms', 'Psalm',
            'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah',
            'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea',
            'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum',
            'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi',
            'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans',
            '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
            'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
            '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews',
            'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
            'Jude', 'Revelation',
            'Gen', 'Ex', 'Lev', 'Num', 'Deut', 'Josh', 'Judg',
            'Ps', 'Prov', 'Eccl', 'Isa', 'Jer', 'Ezek', 'Dan',
            'Matt', 'Mk', 'Lk', 'Jn', 'Rom', 'Gal', 'Eph',
            'Phil', 'Col', 'Rev',
        ];

        $bookPattern = implode('|', array_map(fn($b) => preg_quote($b, '/'), $books));
        $pattern     = '/\b(' . $bookPattern . ')\s+\d+:\d+(?:-\d+)?\b/i';

        preg_match_all($pattern, $text, $matches);

        return array_unique($matches[0] ?? []);
    }
}