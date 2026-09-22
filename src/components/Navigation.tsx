'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  LayoutDashboard,
  Send,
  MessageSquareText,
  Bot,
  Settings,
  Sparkles,
  Zap,
  Users,
  FolderKanban,
  ChevronLeft,
  ChevronRight,
  ShieldCheck,
} from 'lucide-react';

export default function Navigation() {
  const pathname = usePathname();
  const [isCollapsed, setIsCollapsed] = useState(false);

  const navItems = [
    {
      label: 'Dashboard',
      href: '/',
      icon: LayoutDashboard,
      badge: null,
    },
    {
      label: 'Bulk Campaigns',
      href: '/campaigns',
      icon: Send,
      badge: 'Live',
    },
    {
      label: 'Contacts Directory',
      href: '/contacts',
      icon: Users,
      badge: null,
    },
    {
      label: 'Group Master',
      href: '/groups',
      icon: FolderKanban,
      badge: 'Segments',
    },
    {
      label: 'Live Inbox',
      href: '/inbox',
      icon: MessageSquareText,
      badge: '1 New',
    },
    {
      label: 'Auto-Reply & AI Bot',
      href: '/automation',
      icon: Bot,
      badge: 'AI',
    },
    {
      label: 'Settings & API',
      href: '/settings',
      icon: Settings,
      badge: null,
    },
  ];

  return (
    <aside
      className={`border-r border-slate-200 bg-white flex flex-col justify-between sticky top-0 h-screen shrink-0 z-40 transition-all duration-200 ${
        isCollapsed ? 'w-20 p-3' : 'w-64 p-4'
      }`}
    >
      <div>
        {/* Brand Header */}
        <div className="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
          <Link href="/" className="flex items-center gap-3 overflow-hidden">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center shadow-md shadow-emerald-600/10 shrink-0">
              <Zap className="w-5 h-5 text-white fill-white" />
            </div>
            {!isCollapsed && (
              <div className="min-w-0">
                <div className="font-bold text-sm tracking-tight text-slate-900 flex items-center gap-1.5">
                  WA SuperBlast
                  <span className="text-[10px] px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-semibold border border-emerald-200">
                    PRO
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 truncate">Bulk & AI Automation</p>
              </div>
            )}
          </Link>

          <button
            type="button"
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            title={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          >
            {isCollapsed ? (
              <ChevronRight className="w-4 h-4" />
            ) : (
              <ChevronLeft className="w-4 h-4" />
            )}
          </button>
        </div>

        {/* Navigation Links */}
        <nav className="space-y-1">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href;

            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium transition-all group ${
                  isActive
                    ? 'bg-emerald-50 text-emerald-800 font-semibold border border-emerald-200/80 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                }`}
                title={isCollapsed ? item.label : undefined}
              >
                <div className="flex items-center gap-3">
                  <Icon
                    className={`w-4 h-4 shrink-0 transition-colors ${
                      isActive ? 'text-emerald-600' : 'text-slate-500 group-hover:text-slate-700'
                    }`}
                  />
                  {!isCollapsed && <span>{item.label}</span>}
                </div>

                {!isCollapsed && item.badge && (
                  <span
                    className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${
                      item.badge === 'Live'
                        ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                        : item.badge === 'AI'
                        ? 'bg-purple-100 text-purple-700 border border-purple-200'
                        : item.badge === 'Segments'
                        ? 'bg-teal-100 text-teal-700 border border-teal-200'
                        : 'bg-cyan-100 text-cyan-700 border border-cyan-200'
                    }`}
                  >
                    {item.badge}
                  </span>
                )}
              </Link>
            );
          })}
        </nav>
      </div>

      {/* Footer System Status Card */}
      {!isCollapsed ? (
        <div className="space-y-3 pt-4 border-t border-slate-100">
          <div className="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
            <div className="flex items-center justify-between mb-1.5">
              <span className="text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                Anti-Ban Engine
              </span>
              <span className="text-[10px] text-emerald-700 font-semibold bg-emerald-100 px-1.5 py-0.5 rounded">
                Active
              </span>
            </div>
            <p className="text-[11px] text-slate-500 leading-snug">
              Intelligent 2.0s rate throttling active on all broadcast queues.
            </p>
          </div>

          <div className="text-center">
            <p className="text-[10px] text-slate-400 font-mono">
              v1.2.0 • Meta Cloud API Ready
            </p>
          </div>
        </div>
      ) : (
        <div className="py-2 text-center">
          <span className="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse" />
        </div>
      )}
    </aside>
  );
}
