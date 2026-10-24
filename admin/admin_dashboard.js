(function () {
  "use strict";
  const API = "../API/admin_api.php",
    DOC = "../admin/admin_document.php",
    LOGIN = "../index.html";
  let CSRF = "";

  /* ---------- helpers ---------- */
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) =>
    Array.prototype.slice.call((r || document).querySelectorAll(s));
  const esc = (v) =>
    String(v == null ? "" : v).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  // Safari cannot parse "YYYY-MM-DD HH:MM:SS" - the space must become "T"
  const toDate = (s) => new Date(String(s).replace(" ", "T"));
  const fmt = (s, alt) => {
    if (!s) return alt || "—";
    const d = toDate(s);
    return isNaN(d)
      ? String(s)
      : d.toLocaleString("en-PH", {
          year: "numeric",
          month: "short",
          day: "numeric",
          hour: "numeric",
          minute: "2-digit",
        });
  };
  const ago = (s) => {
    const d = toDate(s);
    if (isNaN(d)) return "";
    const m = Math.floor((Date.now() - d) / 60000);
    if (m < 1) return "just now";
    if (m < 60) return m + " min ago";
    const h = Math.floor(m / 60);
    return h < 24 ? h + " hr ago" : Math.floor(h / 24) + " d ago";
  };
  const peso = (n) =>
    "₱" +
    Number(n || 0).toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  const cap = (s) =>
    String(s || "")
      .replace(/_/g, " ")
      .replace(/^./, (c) => c.toUpperCase());
  const trunc = (s, n) => {
    s = String(s || "");
    return s.length > n ? s.slice(0, n) + "…" : s;
  };
  const TONE = {
    active: "ok",
    approved: "ok",
    confirmed: "ok",
    completed: "ok",
    resolved: "ok",
    success: "ok",
    pending: "warn",
    open: "warn",
    investigating: "warn",
    blocked: "bad",
    rejected: "bad",
    suspended: "bad",
    declined: "bad",
    cancelled: "bad",
    failed: "bad",
  };
  const badge = (s) =>
    '<span class="b ' + (TONE[s] || "") + '">' + esc(cap(s)) + "</span>";
  const icon = (id) => '<svg class="ic"><use href="#i-' + id + '"/></svg>';

  let toastTimer;
  function toast(msg, err) {
    const t = $("#toast");
    t.textContent = msg;
    t.className = "toast show" + (err ? " err" : "");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      t.className = "toast";
    }, 3800);
  }

  async function api(action, o) {
    o = o || {};
    const url = new URL(API, location.href);
    url.searchParams.set("action", action);
    Object.keys(o.query || {}).forEach((k) => {
      const v = o.query[k];
      if (v !== "" && v != null) url.searchParams.set(k, v);
    });
    const opt = {
      method: o.body ? "POST" : "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    };
    if (o.body) {
      opt.headers["Content-Type"] = "application/json";
      opt.headers["X-CSRF-Token"] = CSRF;
      opt.body = JSON.stringify(o.body);
    }
    let res;
    try {
      res = await fetch(url.toString(), opt);
    } catch (e) {
      throw new Error("Cannot reach the server. Check your connection.");
    }
    let data = null;
    try {
      data = await res.json();
    } catch (e) {}
    if (res.status === 401) {
      location.href = LOGIN;
      throw new Error("Session expired.");
    }
    if (!res.ok || !data || data.ok === false)
      throw new Error(
        (data && data.error) || "Request failed (" + res.status + ").",
      );
    return data;
  }
  async function busy(btn, fn) {
    btn.disabled = true;
    try {
      return await fn();
    } catch (e) {
      toast(e.message, true);
    } finally {
      btn.disabled = false;
    }
  }

  /* ---------- modal ---------- */
  function modal(title, body, buttons) {
    const root = $("#modalRoot"),
      prev = document.activeElement;
    root.innerHTML =
      '<div class="mroot"><div class="modal" role="dialog" aria-modal="true" aria-label="' +
      esc(title) +
      '">' +
      '<div class="mhead"><h3>' +
      esc(title) +
      '</h3><button class="x" type="button" aria-label="Close">&times;</button></div>' +
      '<div class="mbody">' +
      body +
      '</div><div class="mfoot"></div></div></div>';
    document.body.classList.add("lock");
    function onKey(e) {
      if (e.key === "Escape") m.close();
    }
    const m = {
      close() {
        root.innerHTML = "";
        document.body.classList.remove("lock");
        document.removeEventListener("keydown", onKey);
        if (prev && prev.focus) prev.focus();
      },
    };
    document.addEventListener("keydown", onKey);
    $(".x", root).onclick = m.close;
    $(".mroot", root).addEventListener("click", (e) => {
      if (e.target.classList.contains("mroot")) m.close();
    });
    (buttons || []).forEach((b) => {
      const el = document.createElement("button");
      el.type = "button";
      el.className = "btn " + (b.cls || "");
      el.textContent = b.label;
      el.onclick = () => b.fn(m, el);
      $(".mfoot", root).appendChild(el);
    });
    const first = $("input,select,textarea", root);
    if (first) first.focus();
    return m;
  }
  const ask = (title, msg, ok, cls) =>
    new Promise((res) =>
      modal(title, "<p>" + esc(msg) + "</p>", [
        {
          label: "Cancel",
          fn: (m) => {
            m.close();
            res(false);
          },
        },
        {
          label: ok,
          cls: cls || "pri",
          fn: (m) => {
            m.close();
            res(true);
          },
        },
      ]),
    );

  /* ---------- generic list module ---------- */
  function listModule(c) {
    const st = { q: "", page: 1, f: {} };
    c.filters.forEach((f) => {
      st.f[f.name] = f.def || "";
    });
    const root = $("#module");
    let items = [],
      seq = 0;
    root.innerHTML =
      '<div class="toolbar">' +
      (c.search
        ? '<input class="in form-control" type="search" id="q" placeholder="' +
          esc(c.search) +
          '" aria-label="Search">'
        : "") +
      c.filters
        .map(
          (f) =>
            '<select class="in form-select" data-f="' +
            f.name +
            '" aria-label="' +
            esc(f.label) +
            '">' +
            f.opts
              .map(
                (o) =>
                  '<option value="' +
                  esc(o[0]) +
                  '"' +
                  (st.f[f.name] === o[0] ? " selected" : "") +
                  ">" +
                  esc(o[1]) +
                  "</option>",
              )
              .join("") +
            "</select>",
        )
        .join("") +
      (c.add
        ? '<button class="btn pri" id="add" type="button">' +
          esc(c.add.label) +
          "</button>"
        : "") +
      '</div><div class="panel"><div class="tbl-wrap table-responsive"><table class="tbl table align-middle"><thead><tr>' +
      c.cols.map((x) => "<th>" + esc(x[0]) + "</th>").join("") +
      '</tr></thead><tbody id="rows"></tbody></table></div><div class="pager" id="pager"></div></div>';
    const span = c.cols.length;
    const msg = (t) =>
      '<tr><td class="empty" colspan="' + span + '">' + esc(t) + "</td></tr>";

    async function load() {
      const my = ++seq;
      $("#rows").innerHTML = msg("Loading…");
      try {
        const d = await api(c.action, {
          query: Object.assign({ page: st.page, q: st.q }, st.f),
        });
        if (my !== seq) return;
        items = d.items;
        $("#rows").innerHTML = items.length
          ? items
              .map(
                (r, i) =>
                  "<tr>" +
                  c.cols
                    .map(
                      (x) =>
                        '<td data-label="' +
                        esc(x[0]) +
                        '"' +
                        (x[2] ? ' class="' + x[2] + '"' : "") +
                        ">" +
                        x[1](r, i) +
                        "</td>",
                    )
                    .join("") +
                  "</tr>",
              )
              .join("")
          : msg(c.empty || "Nothing to show.");
        const pages = Math.max(1, Math.ceil(d.total / d.per));
        $("#pager").innerHTML =
          "<span>" +
          d.total +
          " record" +
          (d.total === 1 ? "" : "s") +
          "</span><span>" +
          '<button class="btn sm" data-pg="-1" type="button"' +
          (st.page <= 1 ? " disabled" : "") +
          ">Prev</button> Page " +
          st.page +
          " of " +
          pages +
          ' <button class="btn sm" data-pg="1" type="button"' +
          (st.page >= pages ? " disabled" : "") +
          ">Next</button></span>";
      } catch (e) {
        if (my === seq) $("#rows").innerHTML = msg(e.message);
      }
    }
    const q = $("#q");
    if (q) {
      let t;
      q.addEventListener("input", () => {
        clearTimeout(t);
        t = setTimeout(() => {
          st.q = q.value.trim();
          st.page = 1;
          load();
        }, 350);
      });
    }
    $$("select[data-f]", root).forEach((s) =>
      s.addEventListener("change", () => {
        st.f[s.dataset.f] = s.value;
        st.page = 1;
        load();
      }),
    );
    $("#pager").addEventListener("click", (e) => {
      const b = e.target.closest("button[data-pg]");
      if (b) {
        st.page += +b.dataset.pg;
        load();
      }
    });
    $("#rows").addEventListener("click", (e) => {
      const b = e.target.closest("button[data-a]");
      if (b) c.actions[b.dataset.a](items[+b.dataset.i], load);
    });
    if (c.add) $("#add").onclick = () => c.add.fn(load);
    load();
  }
  const actBtn = (label, a, i, cls) =>
    '<button class="btn sm ' +
    (cls || "") +
    '" type="button" data-a="' +
    a +
    '" data-i="' +
    i +
    '">' +
    label +
    "</button>";
  const opts = (all, list) => [["", all]].concat(list.map((s) => [s, cap(s)]));

  /* ---------- modules ---------- */
  const mods = {
    users: () =>
      listModule({
        action: "users_list",
        search: "Search name or email",
        empty: "No users found.",
        filters: [
          {
            name: "role",
            label: "Role",
            opts: opts("All roles", ["client", "provider"]),
          },
          {
            name: "status",
            label: "Status",
            opts: opts("All statuses", ["active", "blocked"]),
          },
        ],
        cols: [
          [
            "Name",
            (r) => esc(r.full_name) + "<small>" + esc(r.email) + "</small>",
          ],
          ["Role", (r) => esc(cap(r.role))],
          ["Mobile", (r) => esc(r.mobile_number || "—")],
          ["Status", (r) => badge(r.status)],
          ["Last login", (r) => esc(fmt(r.last_login, "Never")), "num"],
          ["Joined", (r) => esc(fmt(r.created_at)), "num"],
          [
            "",
            (r, i) =>
              actBtn(
                r.status === "active" ? "Block" : "Unblock",
                "toggle",
                i,
                r.status === "active" ? "dng" : "",
              ),
            "act",
          ],
        ],
        actions: {
          async toggle(r, reload) {
            const to = r.status === "active" ? "blocked" : "active";
            if (
              !(await ask(
                (to === "blocked" ? "Block " : "Unblock ") + r.full_name + "?",
                to === "blocked"
                  ? "They will not be able to log in."
                  : "They will be able to log in again.",
                cap(to === "blocked" ? "block" : "unblock"),
                to === "blocked" ? "dng" : "pri",
              ))
            )
              return;
            try {
              toast(
                (
                  await api("user_status", {
                    body: { user_id: r.user_id, status: to },
                  })
                ).message,
              );
              reload();
            } catch (e) {
              toast(e.message, true);
            }
          },
        },
      }),

    providers: () =>
      listModule({
        action: "providers_list",
        search: "Search name, email or location",
        empty: "No providers in this view.",
        filters: [
          {
            name: "status",
            label: "Verification status",
            def: "pending",
            opts: opts("All statuses", [
              "pending",
              "approved",
              "rejected",
              "suspended",
            ]),
          },
        ],
        cols: [
          [
            "Provider",
            (r) => esc(r.full_name) + "<small>" + esc(r.email) + "</small>",
          ],
          ["Location", (r) => esc(r.location)],
          ["Services", (r) => esc(r.services || "—"), "clip"],
          ["Docs", (r) => esc(r.docs)],
          [
            "Rating",
            (r) =>
              r.total_reviews > 0
                ? esc(r.avg_rating) + " (" + esc(r.total_reviews) + ")"
                : "—",
          ],
          ["Status", (r) => badge(r.verification_status)],
          ["Applied", (r) => esc(fmt(r.created_at)), "num"],
          ["", (r, i) => actBtn("Review", "review", i, "pri"), "act"],
        ],
        actions: { review: (r, reload) => openProvider(r.provider_id, reload) },
      }),

    bookings: () =>
      listModule({
        action: "bookings_list",
        search: "Search client or provider",
        empty: "No bookings found.",
        filters: [
          {
            name: "status",
            label: "Status",
            opts: opts("All statuses", [
              "pending",
              "confirmed",
              "declined",
              "cancelled",
              "expired",
              "completed",
            ]),
          },
        ],
        cols: [
          ["#", (r) => "#" + esc(r.booking_id)],
          ["Client", (r) => esc(r.client)],
          ["Provider", (r) => esc(r.provider)],
          ["Service", (r) => esc(r.service)],
          [
            "Schedule",
            (r) =>
              esc(fmt(r.start_datetime)) +
              "<small>to " +
              esc(fmt(r.end_datetime)) +
              "</small>",
            "num",
          ],
          ["Type", (r) => esc(cap(r.booking_type))],
          ["Total", (r) => esc(peso(r.total_cost)), "num"],
          [
            "Status",
            (r) =>
              badge(r.status) +
              (r.cancelled_by
                ? "<small>by " + esc(r.cancelled_by) + "</small>"
                : ""),
          ],
        ],
        actions: {},
      }),

    services: () =>
      listModule({
        action: "services_list",
        filters: [],
        empty: "No services yet.",
        add: {
          label: "Add service",
          fn: (reload) => serviceForm(null, reload),
        },
        cols: [
          [
            "Service",
            (r) =>
              esc(r.name) + "<small>" + esc(r.description || "") + "</small>",
          ],
          ["Active providers", (r) => esc(r.providers)],
          [
            "Status",
            (r) =>
              badge(r.is_active == 1 ? "active" : "blocked").replace(
                "Blocked",
                "Inactive",
              ),
          ],
          [
            "",
            (r, i) =>
              actBtn("Edit", "edit", i) +
              " " +
              actBtn(
                r.is_active == 1 ? "Deactivate" : "Activate",
                "toggle",
                i,
                r.is_active == 1 ? "dng" : "",
              ),
            "act",
          ],
        ],
        actions: {
          edit: (r, reload) => serviceForm(r, reload),
          async toggle(r, reload) {
            if (
              r.is_active == 1 &&
              !(await ask(
                "Deactivate " + r.name + "?",
                "Providers will no longer be able to offer it.",
                "Deactivate",
                "dng",
              ))
            )
              return;
            try {
              toast(
                (
                  await api("service_toggle", {
                    body: { category_id: r.category_id },
                  })
                ).message,
              );
              reload();
            } catch (e) {
              toast(e.message, true);
            }
          },
        },
      }),

    reports: () =>
      listModule({
        action: "reports_list",
        search: "Search reports",
        empty: "No reports found.",
        filters: [
          {
            name: "status",
            label: "Status",
            def: "open",
            opts: opts("All statuses", [
              "open",
              "investigating",
              "resolved",
              "dismissed",
            ]),
          },
          {
            name: "type",
            label: "Type",
            opts: opts("All types", ["complaint", "safety_concern", "other"]),
          },
        ],
        cols: [
          ["#", (r) => "#" + esc(r.report_id)],
          ["Type", (r) => esc(cap(r.report_type))],
          [
            "From",
            (r) =>
              esc(r.reporter) +
              "<small>" +
              esc(cap(r.reporter_role)) +
              "</small>",
          ],
          [
            "About",
            (r) =>
              r.reported
                ? esc(r.reported) +
                  "<small>" +
                  esc(cap(r.reported_role)) +
                  "</small>"
                : "—",
          ],
          ["Details", (r) => esc(trunc(r.description, 70)), "clip"],
          ["Status", (r) => badge(r.status)],
          ["Filed", (r) => esc(fmt(r.created_at)), "num"],
          ["", (r, i) => actBtn("Review", "review", i, "pri"), "act"],
        ],
        actions: { review: (r, reload) => openReport(r, reload) },
      }),

    reviews: () =>
      listModule({
        action: "reviews_list",
        search: "Search reviews",
        empty: "No reviews yet.",
        filters: [
          {
            name: "vis",
            label: "Visibility",
            opts: [
              ["", "All reviews"],
              ["visible", "Visible"],
              ["hidden", "Hidden"],
            ],
          },
        ],
        cols: [
          ["Provider", (r) => esc(r.provider)],
          ["Client", (r) => esc(r.client)],
          ["Rating", (r) => esc(r.rating) + " / 5"],
          ["Comment", (r) => esc(trunc(r.comment, 90) || "—"), "clip"],
          ["Date", (r) => esc(fmt(r.created_at)), "num"],
          [
            "Visibility",
            (r) =>
              r.is_hidden == 1
                ? '<span class="b bad">Hidden</span>'
                : '<span class="b ok">Visible</span>',
          ],
          [
            "",
            (r, i) =>
              actBtn(
                r.is_hidden == 1 ? "Show" : "Hide",
                "vis",
                i,
                r.is_hidden == 1 ? "" : "dng",
              ),
            "act",
          ],
        ],
        actions: {
          async vis(r, reload) {
            const hide = r.is_hidden != 1;
            if (
              hide &&
              !(await ask(
                "Hide this review?",
                "It will be removed from the Provider profile and rating.",
                "Hide",
                "dng",
              ))
            )
              return;
            try {
              toast(
                (
                  await api("review_visibility", {
                    body: { review_id: r.review_id, hidden: hide },
                  })
                ).message,
              );
              reload();
            } catch (e) {
              toast(e.message, true);
            }
          },
        },
      }),

    activity: () =>
      listModule({
        action: "logs_list",
        search: "Search action, details or user",
        empty: "No activity yet.",
        filters: [
          {
            name: "outcome",
            label: "Outcome",
            opts: opts("All outcomes", ["success", "failed"]),
          },
        ],
        cols: [
          ["Time", (r) => esc(fmt(r.created_at)), "num"],
          ["User", (r) => esc(r.who || "System / guest")],
          ["Action", (r) => esc(cap(r.action))],
          ["Details", (r) => esc(r.details || "—"), "clip"],
          ["IP", (r) => esc(r.ip_address || "—")],
          ["Outcome", (r) => badge(r.outcome)],
        ],
        actions: {},
      }),

    settings: () => {
      $("#module").innerHTML =
        '<div class="grid row g-3"><section class="panel pad col-12 col-xl-6"><small class="eyebrow">SECURITY</small><h3>Change password</h3>' +
        '<div class="f"><label for="pc">Current password</label><input class="in form-control" id="pc" type="password" autocomplete="current-password"></div>' +
        '<div class="f"><label for="pn">New password (min. 8 characters)</label><input class="in form-control" id="pn" type="password" autocomplete="new-password"></div>' +
        '<div class="f"><label for="pk">Confirm new password</label><input class="in form-control" id="pk" type="password" autocomplete="new-password"></div>' +
        '<button class="btn pri" id="pw" type="button">Update password</button></section>' +
        '<section class="panel pad col-12 col-xl-6"><small class="eyebrow">ANNOUNCEMENTS</small><h3>Send a notification</h3>' +
        '<div class="f"><label for="aa">Audience</label><select class="in form-select" id="aa"><option value="all">Clients and Providers</option><option value="client">Clients only</option><option value="provider">Providers only</option></select></div>' +
        '<div class="f"><label for="at">Title</label><input class="in form-control" id="at" maxlength="120"></div>' +
        '<div class="f"><label for="am">Message</label><textarea class="in form-control" id="am" maxlength="255" rows="3"></textarea></div>' +
        '<button class="btn pri" id="sn" type="button">Send</button></section></div>';
      $("#pw").onclick = (e) => {
        const cur = $("#pc").value,
          nw = $("#pn").value;
        if (nw.length < 8)
          return toast("New password must be at least 8 characters.", true);
        if (nw !== $("#pk").value)
          return toast("Passwords do not match.", true);
        busy(e.currentTarget, async () => {
          toast(
            (await api("change_password", { body: { current: cur, new: nw } }))
              .message,
          );
          ["#pc", "#pn", "#pk"].forEach((s) => {
            $(s).value = "";
          });
        });
      };
      $("#sn").onclick = async (e) => {
        const b = e.currentTarget,
          body = {
            audience: $("#aa").value,
            title: $("#at").value,
            message: $("#am").value,
          };
        if (!body.title.trim() || !body.message.trim())
          return toast("Title and message are required.", true);
        if (
          !(await ask(
            "Send announcement?",
            "This notifies every active user in the chosen audience.",
            "Send",
          ))
        )
          return;
        busy(b, async () => {
          toast((await api("announce", { body })).message);
          $("#at").value = "";
          $("#am").value = "";
        });
      };
    },
  };

  /* ---------- modals: provider / report / service ---------- */
  const DT = {
    government_id: "Government ID",
    nbi_clearance: "NBI clearance",
    police_clearance: "Police clearance",
    certification: "Certification",
  };

  async function openProvider(id, reload) {
    let p;
    try {
      p = (await api("provider_detail", { query: { id } })).provider;
    } catch (e) {
      return toast(e.message, true);
    }
    const rq = p.requirements;
    const ck = (ok, t) =>
      '<li class="ck ' + (ok ? "ok" : "") + '">' + esc(t) + "</li>";
    const body =
      '<dl class="dl"><dt>Name</dt><dd>' +
      esc(p.full_name) +
      "</dd><dt>Email</dt><dd>" +
      esc(p.email) +
      "</dd><dt>Mobile</dt><dd>" +
      esc(p.mobile_number || "—") +
      "</dd>" +
      "<dt>Location</dt><dd>" +
      esc(p.location) +
      "</dd><dt>Experience</dt><dd>" +
      esc(p.experience_years) +
      " year(s)</dd>" +
      "<dt>Skills</dt><dd>" +
      esc(p.skills || "—") +
      "</dd><dt>Qualifications</dt><dd>" +
      esc(p.qualifications || "—") +
      "</dd>" +
      "<dt>Services</dt><dd>" +
      (p.services.length
        ? p.services
            .map(
              (s) =>
                esc(s.name) +
                " (" +
                esc(peso(s.hourly_rate)) +
                "/hr)" +
                (s.is_active == 1 ? "" : " – inactive"),
            )
            .join("<br>")
        : "—") +
      "</dd>" +
      "<dt>Status</dt><dd>" +
      badge(p.verification_status) +
      "</dd>" +
      (p.verification_remarks
        ? "<dt>Last note</dt><dd>" + esc(p.verification_remarks) + "</dd>"
        : "") +
      "</dl>" +
      '<h4>Requirements</h4><ul class="checks">' +
      ck(rq.has_id, "Government ID " + (rq.has_id ? "provided" : "missing")) +
      (rq.needs_clearance
        ? ck(
            rq.has_clearance,
            "NBI or police clearance " +
              (rq.has_clearance ? "provided" : "missing") +
              " (required for childcare / senior care)",
          )
        : '<li class="ck ok">Clearance not required for these services</li>') +
      "</ul>" +
      '<h4>Submitted documents</h4><div class="docs">' +
      (p.documents.length
        ? p.documents
            .map(
              (d) =>
                '<div class="doc"><span>' +
                esc(DT[d.doc_type] || d.doc_type) +
                "<small>" +
                esc(fmt(d.uploaded_at)) +
                "</small></span>" +
                '<a class="btn sm" target="_blank" rel="noopener" href="' +
                DOC +
                "?id=" +
                d.document_id +
                '">View</a></div>',
            )
            .join("")
        : '<p class="muted">No documents uploaded.</p>') +
      "</div>" +
      '<h4>Note to Provider</h4><div class="f"><textarea class="in form-control" id="rem" rows="3" maxlength="150" aria-label="Note to provider" placeholder="Required when rejecting or suspending"></textarea></div>';
    const decide = (dec) => async (m, btn) =>
      busy(btn, async () => {
        const d = await api("provider_decide", {
          body: {
            provider_id: p.provider_id,
            decision: dec,
            remarks: $("#rem").value,
          },
        });
        toast(d.message);
        m.close();
        reload();
        refreshBadges();
      });
    const s = p.verification_status,
      btns = [{ label: "Close", fn: (m) => m.close() }];
    if (s === "pending")
      btns.push(
        { label: "Reject", cls: "dng", fn: decide("reject") },
        { label: "Approve", cls: "pri", fn: decide("approve") },
      );
    if (s === "rejected")
      btns.push({ label: "Approve", cls: "pri", fn: decide("approve") });
    if (s === "approved")
      btns.push({ label: "Suspend", cls: "dng", fn: decide("suspend") });
    if (s === "suspended")
      btns.push({ label: "Reinstate", cls: "pri", fn: decide("reinstate") });
    modal("Provider review", body, btns);
  }

  function openReport(r, reload) {
    modal(
      "Report #" + r.report_id,
      '<dl class="dl"><dt>Type</dt><dd>' +
        esc(cap(r.report_type)) +
        "</dd><dt>From</dt><dd>" +
        esc(r.reporter) +
        " (" +
        esc(cap(r.reporter_role)) +
        ")</dd>" +
        "<dt>About</dt><dd>" +
        (r.reported
          ? esc(r.reported) + " (" + esc(cap(r.reported_role)) + ")"
          : "—") +
        "</dd>" +
        "<dt>Booking</dt><dd>" +
        (r.booking_id ? "#" + esc(r.booking_id) : "—") +
        "</dd><dt>Filed</dt><dd>" +
        esc(fmt(r.created_at)) +
        "</dd></dl>" +
        '<h4>Description</h4><div class="box">' +
        esc(r.description) +
        "</div><h4>Resolution</h4>" +
        '<div class="f"><label for="rs">Status</label><select class="in form-select" id="rs">' +
        ["open", "investigating", "resolved", "dismissed"]
          .map(
            (s) =>
              '<option value="' +
              s +
              '"' +
              (s === r.status ? " selected" : "") +
              ">" +
              cap(s) +
              "</option>",
          )
          .join("") +
        "</select></div>" +
        '<div class="f"><label for="rn">Admin notes (required when resolving or dismissing)</label><textarea class="in form-control" id="rn" rows="4">' +
        esc(r.admin_notes || "") +
        "</textarea></div>",
      [
        { label: "Close", fn: (m) => m.close() },
        {
          label: "Save",
          cls: "pri",
          fn: (m, b) =>
            busy(b, async () => {
              const d = await api("report_update", {
                body: {
                  report_id: r.report_id,
                  status: $("#rs").value,
                  notes: $("#rn").value,
                },
              });
              toast(d.message);
              m.close();
              reload();
              refreshBadges();
            }),
        },
      ],
    );
  }

  function serviceForm(r, reload) {
    modal(
      r ? "Edit service" : "Add service",
      '<div class="f"><label for="sn1">Name</label><input class="in form-control" id="sn1" maxlength="80" value="' +
        esc(r ? r.name : "") +
        '"></div>' +
        '<div class="f"><label for="sd1">Description</label><textarea class="in form-control" id="sd1" rows="3" maxlength="255">' +
        esc(r ? r.description : "") +
        "</textarea></div>",
      [
        { label: "Cancel", fn: (m) => m.close() },
        {
          label: "Save",
          cls: "pri",
          fn: (m, b) =>
            busy(b, async () => {
              const d = await api("service_save", {
                body: {
                  category_id: r ? r.category_id : 0,
                  name: $("#sn1").value,
                  description: $("#sd1").value,
                },
              });
              toast(d.message);
              m.close();
              reload();
            }),
        },
      ],
    );
  }

  /* ---------- dashboard ---------- */
  function applyBadges(d) {
    const set = (id, n) => {
      const e = $(id);
      e.textContent = n;
      e.classList.toggle("hidden", !n);
    };
    set("#bdgProv", d.providers_pending);
    set("#bdgRep", d.reports_open);
    $("#bellDot").classList.toggle(
      "hidden",
      !(d.providers_pending || d.reports_open),
    );
    window.__counts = d;
  }
  const refreshBadges = () =>
    api("stats")
      .then(applyBadges)
      .catch(() => {});

  async function dashboard() {
    try {
      const d = await api("stats");
      applyBadges(d);
      $("#sUsers").textContent = d.users_total;
      $("#sUsersSub").innerHTML = "<b>+" + d.users_month + "</b> this month";
      $("#sProv").textContent = d.providers_total;
      $("#sProvSub").innerHTML =
        "<b>" + d.providers_pending + "</b> pending verification";
      $("#sBook").textContent = d.bookings_total;
      $("#sBookSub").innerHTML =
        "<b>" + d.bookings_today + "</b> confirmed today";
      $("#sRep").textContent = d.reports_open;
      $("#sRepSub").textContent = d.reports_open
        ? "Needs attention"
        : "All clear";

      const att = [];
      if (d.providers_pending)
        att.push([
          "gold",
          "shield",
          "Provider verification",
          d.providers_pending + " provider(s) waiting for document review.",
          "providers",
        ]);
      if (d.reports_open)
        att.push([
          "red",
          "flag",
          "Open complaints",
          d.reports_open + " report(s) need investigation or resolution.",
          "reports",
        ]);
      att.push([
        "green",
        "star",
        "Review moderation",
        "Check new reviews for policy violations.",
        "reviews",
      ]);
      $("#attention").innerHTML = att
        .map(
          (a) =>
            '<button type="button" data-go="' +
            a[4] +
            '"><i class="a ' +
            a[0] +
            '">' +
            icon(a[1]) +
            "</i><div><b>" +
            a[2] +
            "</b><small>" +
            esc(a[3]) +
            '</small></div><svg class="ic chev"><use href="#i-menu"/></svg></button>',
        )
        .join("");
      $$(".chev", $("#attention")).forEach((e) => {
        e.innerHTML = '<path d="M9 18l6-6-6-6" fill="none"/>';
      });

      const tot = Math.max(1, d.bookings_total);
      $("#bars").innerHTML = [
        "pending",
        "confirmed",
        "completed",
        "declined",
        "cancelled",
        "expired",
      ]
        .map(
          (k) =>
            "<div><span>" +
            cap(k) +
            '</span><i><b style="width:' +
            Math.round((d.by_status[k] / tot) * 100) +
            '%"></b></i><strong>' +
            d.by_status[k] +
            "</strong></div>",
        )
        .join("");

      $("#recent").innerHTML = d.recent.length
        ? d.recent
            .map(
              (r) =>
                '<div><i class="dot ' +
                (r.outcome === "failed" ? "red" : "green") +
                '"></i><p><b>' +
                esc(cap(r.action)) +
                "</b><small>" +
                esc(r.who || "System") +
                (r.details ? " · " + esc(r.details) : "") +
                "</small></p><time>" +
                esc(ago(r.created_at)) +
                "</time></div>",
            )
            .join("")
        : '<div class="empty-note">No activity yet.</div>';
    } catch (e) {
      toast(e.message, true);
    }
  }

  /* ---------- navigation ---------- */
  const TITLES = {
    dashboard: "Dashboard",
    users: "User Management",
    providers: "Provider Verification",
    bookings: "Bookings",
    services: "Services",
    reports: "Reports & Complaints",
    reviews: "Reviews",
    activity: "Activity Logs",
    settings: "Settings",
  };
  const sb = $("#sidebar"),
    bd = $("#backdrop"),
    mn = $("#menu");
  function openNav() {
    sb.classList.add("open");
    bd.classList.add("show");
    mn.setAttribute("aria-expanded", "true");
  }
  function closeNav() {
    sb.classList.remove("open");
    bd.classList.remove("show");
    mn.setAttribute("aria-expanded", "false");
  }
  mn.onclick = () => (sb.classList.contains("open") ? closeNav() : openNav());
  bd.onclick = closeNav;
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeNav();
  });

  function go(name) {
    if (!TITLES[name]) name = "dashboard";
    $$(".nav").forEach((b) => {
      const on = b.dataset.section === name;
      b.classList.toggle("active", on);
      if (on) b.setAttribute("aria-current", "page");
      else b.removeAttribute("aria-current");
    });
    $("#title").textContent = TITLES[name];
    document.title = TITLES[name] + " | I'll BeCare Admin";
    const home = name === "dashboard";
    $("#home").classList.toggle("hidden", !home);
    $("#module").classList.toggle("hidden", home);
    closeNav();
    window.scrollTo(0, 0);
    if (home) dashboard();
    else mods[name]();
  }
  document.addEventListener("click", (e) => {
    const t = e.target.closest("[data-section],[data-go]");
    if (!t) return;
    const name = t.dataset.section || t.dataset.go;
    if (location.hash === "#" + name) go(name);
    else location.hash = name;
  });
  window.addEventListener("hashchange", () =>
    go(location.hash.replace("#", "")),
  );
  $("#bell").onclick = () => {
    const c = window.__counts || {};
    toast(
      (c.providers_pending || 0) +
        " provider(s) pending, " +
        (c.reports_open || 0) +
        " open report(s).",
    );
  };
  $("#logout").onclick = async () => {
    try {
      await api("logout", { body: {} });
    } catch (e) {}
    location.href = LOGIN;
  };

  /* ---------- init ---------- */
  (async function init() {
    try {
      const d = await api("init");
      CSRF = d.csrf;
      $("#adminName").textContent = d.name;
      $("#initials").textContent = d.name
        .split(" ")
        .map((w) => w[0])
        .join("")
        .slice(0, 2)
        .toUpperCase();
    } catch (e) {
      return toast(e.message, true);
    }
    const h = new Date().getHours();
    $("#greet").textContent =
      (h < 12 ? "Good morning" : h < 18 ? "Good afternoon" : "Good evening") +
      ", Administrator.";
    $("#date").textContent = new Date().toLocaleDateString("en-PH", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
    go(location.hash.replace("#", ""));
    setInterval(() => {
      if (!document.hidden) refreshBadges();
    }, 60000);
  })();
})();
