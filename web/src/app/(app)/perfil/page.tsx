import { Suspense } from "react";
import { redirect } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { ProfileHeaderSkeleton } from "@/components/profile/ProfileHeader";

// "/perfil" é um atalho: descobre o @ de quem está logado e abre o perfil dele.
export default function MyProfilePage() {
  return (
    <Suspense fallback={<ProfileHeaderSkeleton />}>
      <GoToMyProfile />
    </Suspense>
  );
}

async function GoToMyProfile() {
  const user = await requireUser();
  redirect(`/u/${user.handle}`);
  return null;
}
