import { Suspense } from "react";
import { ProfileHeader, ProfileHeaderSkeleton } from "@/components/profile/ProfileHeader";

export default function ProfileLayout({ children, params }: LayoutProps<"/u/[handle]">) {
  return (
    <div className="flex flex-col gap-8">
      <Suspense fallback={<ProfileHeaderSkeleton />}>
        <ProfileHeader params={params} />
      </Suspense>
      <div className="px-4 sm:px-0">{children}</div>
    </div>
  );
}
