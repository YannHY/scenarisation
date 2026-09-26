#!/usr/bin/env node

import { spawn, spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { mkdir, mkdtemp, rm, writeFile } from "node:fs/promises";
import net from "node:net";
import os from "node:os";
import path from "node:path";
import process from "node:process";

const projectDir = path.resolve(process.argv[2] || process.cwd());
const stagingDir = path.resolve(process.argv[3] || path.join(projectDir, ".pagefind-site"));

if (typeof WebSocket === "undefined") {
  throw new Error("Node.js 22 ou une version ultérieure est nécessaire pour piloter Chrome pendant la construction de l'index.");
}

const pages = {
  fr: [
    "index.php",
    "about.php",
    "bloom.php",
    "cadre-conversationnel.php",
    "competencies.php",
    "help.php",
    "learning-design.php",
    "conditions-utilisation.php",
    "mentions-legales.php",
    "models.php",
    "politique-confidentialite.php",
    "prompts.php"
  ],
  // Ces pages disposent d'une traduction anglaise complète de leur contenu principal.
  en: [
    "index.php",
    "about.php",
    "bloom.php",
    "competencies.php",
    "help.php",
    "learning-design.php",
    "models.php",
    "prompts.php"
  ]
};

function findExecutable(candidates) {
  for (const candidate of candidates) {
    if (!candidate) continue;
    if (candidate.includes(path.sep) && existsSync(candidate)) return candidate;
    if (!candidate.includes(path.sep)) {
      const found = spawnSync("which", [candidate], { encoding: "utf8" });
      if (found.status === 0 && found.stdout.trim()) return found.stdout.trim();
    }
  }
  return null;
}

async function availablePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.unref();
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      server.close(() => resolve(address.port));
    });
  });
}

async function waitForJson(url, attempts = 100) {
  let lastError;
  for (let attempt = 0; attempt < attempts; attempt += 1) {
    try {
      const response = await fetch(url);
      if (response.ok) return response.json();
    } catch (error) {
      lastError = error;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  throw lastError || new Error(`Le service n'a pas répondu : ${url}`);
}

async function waitForUrl(url, attempts = 100) {
  let lastError;
  for (let attempt = 0; attempt < attempts; attempt += 1) {
    try {
      const response = await fetch(url);
      if (response.ok) return;
    } catch (error) {
      lastError = error;
    }
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  throw lastError || new Error(`Le site n'a pas répondu : ${url}`);
}

async function stopProcess(child) {
  if (!child || child.exitCode !== null) return;
  const exited = new Promise((resolve) => child.once("exit", resolve));
  child.kill("SIGTERM");
  await Promise.race([
    exited,
    new Promise((resolve) => setTimeout(resolve, 2000))
  ]);
  if (child.exitCode === null) child.kill("SIGKILL");
}

class CdpConnection {
  constructor(url) {
    this.url = url;
    this.nextId = 1;
    this.pending = new Map();
    this.listeners = new Map();
  }

  async connect() {
    this.socket = new WebSocket(this.url);
    await new Promise((resolve, reject) => {
      this.socket.addEventListener("open", resolve, { once: true });
      this.socket.addEventListener("error", reject, { once: true });
    });
    this.socket.addEventListener("message", (event) => {
      const message = JSON.parse(event.data);
      if (message.id) {
        const callback = this.pending.get(message.id);
        if (!callback) return;
        this.pending.delete(message.id);
        if (message.error) callback.reject(new Error(message.error.message));
        else callback.resolve(message.result || {});
        return;
      }
      const callbacks = this.listeners.get(message.method) || [];
      this.listeners.delete(message.method);
      callbacks.forEach((callback) => callback(message.params || {}));
    });
  }

  send(method, params = {}) {
    const id = this.nextId;
    this.nextId += 1;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.socket.send(JSON.stringify({ id, method, params }));
    });
  }

  waitFor(method, timeout = 15000) {
    return new Promise((resolve, reject) => {
      const callbacks = this.listeners.get(method) || [];
      const timer = setTimeout(() => reject(new Error(`Délai dépassé en attendant ${method}`)), timeout);
      callbacks.push((params) => {
        clearTimeout(timer);
        resolve(params);
      });
      this.listeners.set(method, callbacks);
    });
  }

  close() {
    if (this.socket && this.socket.readyState < 2) this.socket.close();
  }
}

async function renderLanguage(debugPort, siteUrl, language) {
  const targetResponse = await fetch(`http://127.0.0.1:${debugPort}/json/new?about:blank`, {
    method: "PUT"
  });
  if (!targetResponse.ok) throw new Error("Impossible de créer un onglet Chrome pour le rendu.");
  const target = await targetResponse.json();
  const cdp = new CdpConnection(target.webSocketDebuggerUrl);
  await cdp.connect();
  await cdp.send("Page.enable");
  await cdp.send("Runtime.enable");
  await cdp.send("Page.addScriptToEvaluateOnNewDocument", {
    source: `try { localStorage.setItem("learningDesignerLang", ${JSON.stringify(language)}); } catch (error) {}`
  });

  try {
    for (const filename of pages[language]) {
      const loaded = cdp.waitFor("Page.loadEventFired");
      const navigation = await cdp.send("Page.navigate", {
        url: new URL(filename, siteUrl).href
      });
      if (navigation.errorText) throw new Error(`${filename} : ${navigation.errorText}`);
      await loaded;
      await cdp.send("Runtime.evaluate", {
        expression: "new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))",
        awaitPromise: true,
        returnByValue: true
      });

      const rendered = await cdp.send("Runtime.evaluate", {
        expression: `(() => {
          document.documentElement.lang = ${JSON.stringify(language)};
          document.querySelectorAll("[data-prompt-lang]").forEach((element) => {
            if (element.dataset.promptLang !== ${JSON.stringify(language)}) {
              element.remove();
            } else {
              element.removeAttribute("hidden");
            }
          });
          return "<!doctype html>\\n" + document.documentElement.outerHTML;
        })()`,
        returnByValue: true
      });
      const html = rendered.result && rendered.result.value;
      if (!html || !html.includes("<main")) {
        throw new Error(`${filename} n'a pas produit de contenu HTML indexable.`);
      }
      await writeFile(path.join(stagingDir, language, filename), html, "utf8");
      process.stdout.write(`  ${language.toUpperCase()}  ${filename}\n`);
    }
  } finally {
    cdp.close();
    await fetch(`http://127.0.0.1:${debugPort}/json/close/${target.id}`).catch(() => {});
  }
}

const chrome = findExecutable([
  process.env.CHROME_BIN,
  "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
  "/Applications/Chromium.app/Contents/MacOS/Chromium",
  "google-chrome",
  "google-chrome-stable",
  "chromium",
  "chromium-browser"
]);

if (!chrome) {
  throw new Error("Google Chrome ou Chromium est nécessaire pour générer les variantes française et anglaise.");
}

const php = findExecutable([process.env.PHP_BIN, "php"]);
if (!php) throw new Error("PHP est nécessaire pour rendre les pages à indexer.");

await mkdir(path.join(stagingDir, "fr"), { recursive: true });
await mkdir(path.join(stagingDir, "en"), { recursive: true });

const phpPort = await availablePort();
const debugPort = await availablePort();
const siteUrl = `http://127.0.0.1:${phpPort}/`;
const profileDir = await mkdtemp(path.join(os.tmpdir(), "learning-designer-pagefind-"));
const phpServer = spawn(php, ["-S", `127.0.0.1:${phpPort}`, "-t", projectDir], {
  cwd: projectDir,
  env: { ...process.env, APP_BASE_URL: siteUrl },
  stdio: "ignore"
});
const chromeServer = spawn(chrome, [
  "--headless=new",
  "--disable-gpu",
  "--no-first-run",
  "--no-default-browser-check",
  `--remote-debugging-port=${debugPort}`,
  `--user-data-dir=${profileDir}`,
  "about:blank"
], { stdio: "ignore" });

try {
  await waitForJson(`http://127.0.0.1:${debugPort}/json/version`);
  await waitForUrl(siteUrl);
  process.stdout.write("Rendu des pages dans les deux langues :\n");
  await renderLanguage(debugPort, siteUrl, "fr");
  await renderLanguage(debugPort, siteUrl, "en");
} finally {
  await Promise.all([stopProcess(phpServer), stopProcess(chromeServer)]);
  await rm(profileDir, { recursive: true, force: true, maxRetries: 3, retryDelay: 100 });
}
