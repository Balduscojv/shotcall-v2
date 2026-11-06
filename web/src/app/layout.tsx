import type { Metadata } from "next";
import { Anton, Bungee, Manrope, Permanent_Marker } from "next/font/google";
import "./globals.css";

const manrope = Manrope({ variable: "--font-manrope", subsets: ["latin"] });
const bungee = Bungee({ variable: "--font-bungee", weight: "400", subsets: ["latin"] });
const anton = Anton({ variable: "--font-anton", weight: "400", subsets: ["latin"] });
const marker = Permanent_Marker({ variable: "--font-marker", weight: "400", subsets: ["latin"] });

export const metadata: Metadata = {
  title: { default: "ShotCall", template: "%s · ShotCall" },
  description: "NBA ao vivo, shot calls por jogo, semana e temporada, fantasy da rodada e ligas com a galera.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="pt-BR"
      className={`${manrope.variable} ${bungee.variable} ${anton.variable} ${marker.variable} h-full antialiased`}
    >
      <body className="concrete flex min-h-full flex-col">{children}</body>
    </html>
  );
}
