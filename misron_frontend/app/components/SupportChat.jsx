"use client";

/**
 * Floating support chat, pinned to the bottom-right corner.
 *
 * Follows the LORÉVA reference design (see reference-design.md §2.15): a dark
 * round launcher with a gold message icon and a green "online" dot, a small
 * "How can we assist you today?" prompt above it until the visitor opens or
 * dismisses it, and a panel with a dark header, the helpline strip, the message
 * thread and an input.
 *
 * There is no chat backend. As in the reference, messages stay in the browser
 * and an automatic reply points the visitor to the helpline, so the widget is
 * hidden when the admin has not set a contact phone.
 *
 * Reads `/footer-data` through the shared cache, like the header strip and the
 * footer, so it adds no request of its own.
 */

import { useEffect, useRef, useState } from "react";
import { MessageSquare, Phone, Send, X } from "lucide-react";

import useCachedFetch from "../utils/useCachedFetch";

/** Delay before the automatic reply, so it reads as a response. */
const REPLY_DELAY_MS = 700;

const timeNow = () =>
  new Date().toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });

export default function SupportChat() {
  const { data: footerData } = useCachedFetch(
    `${process.env.NEXT_PUBLIC_API_URL}/footer-data`,
    "footer-data",
    30 * 60 * 1000,
  );

  const [isOpen, setIsOpen] = useState(false);
  const [promptDismissed, setPromptDismissed] = useState(false);
  const [draft, setDraft] = useState("");
  const [messages, setMessages] = useState(() => [
    {
      sender: "agent",
      text: "Hello! How may we assist you with our collections or order status today?",
      time: timeNow(),
    },
  ]);

  const threadRef = useRef(null);
  const inputRef = useRef(null);
  const replyTimer = useRef(null);

  const helpline = footerData?.contact_phone;

  // Keep the newest message in view.
  useEffect(() => {
    const thread = threadRef.current;
    if (thread) thread.scrollTop = thread.scrollHeight;
  }, [messages, isOpen]);

  // Focus the input on open, and let Escape close the panel.
  useEffect(() => {
    if (!isOpen) return;
    inputRef.current?.focus();

    const onKeyDown = (event) => {
      if (event.key === "Escape") setIsOpen(false);
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [isOpen]);

  useEffect(() => () => clearTimeout(replyTimer.current), []);

  if (!helpline) return null;

  const telHref = `tel:${String(helpline).replace(/[^\d+]/g, "")}`;

  const openChat = () => {
    setIsOpen(true);
    setPromptDismissed(true);
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    const text = draft.trim();
    if (!text) return;

    setMessages((prev) => [...prev, { sender: "user", text, time: timeNow() }]);
    setDraft("");

    clearTimeout(replyTimer.current);
    replyTimer.current = setTimeout(() => {
      setMessages((prev) => [
        ...prev,
        {
          sender: "agent",
          text: `Thank you for your message! For quick help with an order, please call our helpline at ${helpline}. Our team is happy to assist.`,
          time: timeNow(),
        },
      ]);
    }, REPLY_DELAY_MS);
  };

  return (
    <div className="fixed bottom-5 right-5 z-40 flex flex-col items-end">
      {isOpen && (
        <div
          role="dialog"
          aria-label="Customer support chat"
          className="animate-scale-in mb-3 w-[min(24rem,calc(100vw-2.5rem))] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl sm:w-96"
        >
          <div className="flex items-center justify-between bg-stone-900 p-3.5 text-white">
            <div className="flex items-center gap-2">
              <div className="relative">
                <div className="flex size-8 items-center justify-center rounded-full bg-amber-600 text-xs font-bold">
                  M
                </div>
                <span
                  className="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-stone-900 bg-emerald-400"
                  aria-hidden="true"
                />
              </div>
              <div>
                <h4 className="text-xs font-semibold">Customer Support Desk</h4>
                <p className="text-[10px] text-stone-300">Online Now · Avg. response: 2 mins</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              aria-label="Close support chat"
              className="rounded-full p-1 text-stone-400 transition-colors duration-fast hover:bg-stone-800 hover:text-white"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          </div>

          <a
            href={telHref}
            className="flex items-center justify-between border-b border-amber-200/60 bg-amber-50 px-3 py-1.5 text-[11px] text-amber-900 transition-colors duration-fast hover:bg-amber-100/70"
          >
            <span className="flex items-center gap-1 font-medium">
              <Phone className="size-3" aria-hidden="true" />
              Helpline: <span className="tabular">{helpline}</span>
            </span>
            <span className="text-[10px] font-semibold text-emerald-700">Call Now</span>
          </a>

          <div
            ref={threadRef}
            aria-live="polite"
            className="h-64 space-y-2.5 overflow-y-auto bg-[#FAF8F5] p-3 text-xs"
          >
            {messages.map((message, index) => {
              const fromUser = message.sender === "user";
              return (
                <div
                  key={index}
                  className={`flex flex-col ${fromUser ? "items-end" : "items-start"}`}
                >
                  <div
                    className={`max-w-[82%] rounded-xl p-2.5 leading-relaxed ${
                      fromUser
                        ? "rounded-br-none bg-stone-900 text-white"
                        : "rounded-bl-none border border-stone-200 bg-white text-stone-800 shadow-xs"
                    }`}
                  >
                    {message.text}
                  </div>
                  <span className="mt-0.5 px-1 font-mono text-[9px] text-stone-400">
                    {message.time}
                  </span>
                </div>
              );
            })}
          </div>

          <form
            onSubmit={handleSubmit}
            className="flex gap-2 border-t border-stone-200 bg-white p-2.5"
          >
            {/* `type-search-pill` keeps the field at 16px on a phone, so iOS
                Safari does not zoom the page when it takes focus. The global
                `:focus-visible` ring is unlayered and beats `focus:outline-none`,
                so it is removed with `!`, as on the header search; the gold
                border alone marks focus. */}
            <input
              ref={inputRef}
              type="text"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              placeholder="Type your message..."
              aria-label="Type your message"
              className="type-search-pill min-w-0 flex-1 rounded-lg border border-stone-200 bg-stone-50 px-3 py-1.5 transition-colors duration-fast focus:border-amber-700 focus-visible:outline-none!"
            />
            <button
              type="submit"
              aria-label="Send message"
              className="rounded-lg bg-stone-900 p-2.5 text-white transition-colors duration-fast hover:bg-black"
            >
              <Send className="size-3.5" aria-hidden="true" />
            </button>
          </form>
        </div>
      )}

      {!isOpen && !promptDismissed && (
        <div className="animate-slide-up relative mb-2 flex items-center gap-2 rounded-2xl border border-stone-200/90 bg-white px-3.5 py-2 text-xs font-medium text-stone-800 shadow-xl">
          <button
            type="button"
            onClick={() => setPromptDismissed(true)}
            aria-label="Dismiss chat prompt"
            className="-ml-1 p-0.5 text-stone-400 transition-colors duration-fast hover:text-stone-700"
          >
            <X className="size-3" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={openChat}
            className="text-left transition-colors duration-fast hover:text-amber-800"
          >
            How can we assist you today?
          </button>
          <span
            className="absolute -bottom-1.5 right-6 size-3 rotate-45 border-b border-r border-stone-200 bg-white"
            aria-hidden="true"
          />
        </div>
      )}

      <button
        type="button"
        onClick={() => (isOpen ? setIsOpen(false) : openChat())}
        aria-label={isOpen ? "Close support chat" : "Open support chat"}
        aria-expanded={isOpen}
        className="relative flex size-12 items-center justify-center rounded-full border border-stone-700 bg-[#181614] text-white shadow-xl transition-transform duration-fast hover:scale-105 hover:bg-black active:scale-95"
      >
        <MessageSquare className="size-5 text-amber-400" aria-hidden="true" />
        <span
          className="absolute right-1 top-1 size-2.5 rounded-full border-2 border-stone-900 bg-emerald-500"
          aria-hidden="true"
        />
      </button>
    </div>
  );
}
