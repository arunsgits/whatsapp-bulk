import type { Metadata } from 'next';
import './globals.css';
import Navigation from '@/components/Navigation';
import TopBar from '@/components/TopBar';

export const metadata: Metadata = {
  title: 'WhatsApp SuperBlast - Enterprise Bulk Broadcast & AI Auto-Reply',
  description:
    'Modern WhatsApp Cloud API bulk messaging, contact segment manager, and dynamic AI auto-replies powered by Next.js and Supabase.',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className="bg-slate-50 text-slate-900 antialiased min-h-screen selection:bg-emerald-500 selection:text-white">
        <div className="flex min-h-screen">
          <Navigation />

          {/* Main Layout Area */}
          <div className="flex-1 min-w-0 min-h-screen flex flex-col bg-mesh-pattern">
            <TopBar />
            <main className="flex-1 p-8 max-w-7xl w-full mx-auto">
              {children}
            </main>
          </div>
        </div>
      </body>
    </html>
  );
}
