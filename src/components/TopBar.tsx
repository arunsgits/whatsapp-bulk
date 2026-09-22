'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Send,
  Zap,
  Sparkles,
  Database,
  ExternalLink,
  ChevronRight,
  ShieldCheck,
} from 'lucide-react';

export default function TopBar() {
  const pathname = usePathname();

  const getPageTitle = () => {
    switch (pathname) {
      case '/':
        return 'System Overview & Analytics';
      case '/campaigns':
        return 'Bulk Campaigns Broadcast';
      case '/contacts':
        return 'Contacts Directory';
      case '/groups':
        return 'Group Master Segments';
      case '/inbox':
        return 'Live WhatsApp Inbox';
      case '/automation':
        return 'Dynamic Auto-Reply & AI Bot';
      case '/settings':
        return 'Settings & API Configuration';
      default:
        return 'WhatsApp SuperBlast';
    }
  };

  return (
    <header className="h-16 border-b border-slate-200 bg-white/95 backdrop-blur-md sticky top-0 z-30 flex items-center justify-between px-8">
      {/* Breadcrumb path */}
      <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
        <Link href="/" className="hover:text-slate-900 transition-colors">
          Home
        </Link>
        <ChevronRight className="w-3.5 h-3.5 text-slate-400" />
        <span className="text-slate-900 font-semibold">{getPageTitle()}</span>
      </div>

      {/* Live System Health Pills & Quick Action */}
      <div className="flex items-center gap-3">
        {/* Meta Cloud API status */}
        <div className="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium">
          <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
          <span>Meta API v19.0</span>
        </div>

        {/* AI Bot status */}
        <div className="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 border border-purple-200 text-purple-700 text-xs font-medium">
          <Sparkles className="w-3 h-3 text-purple-500" />
          <span>AI Bot Online</span>
        </div>

        {/* Quick broadcast CTA */}
        {pathname !== '/campaigns' && (
          <Link
            href="/campaigns"
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-all hover:shadow"
          >
            <Send className="w-3.5 h-3.5" />
            <span>New Blast</span>
          </Link>
        )}
      </div>
    </header>
  );
}
