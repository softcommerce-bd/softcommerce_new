"use client";
import { useEffect, useRef, useState } from "react";
import { useSelector, useDispatch } from "react-redux";
import { closeVideoModal } from "@/store/slices/videoModalSlice";
import Link from "next/link";
import { Play, Share2, Volume2, VolumeX, X } from "lucide-react";
import { tkText } from "@/utils/currency";

export default function VideoModal() {
  const { isOpen, videoItem } = useSelector((state) => state.videoModal);
  const dispatch = useDispatch();
  const [isMuted, setIsMuted] = useState(true);
  const [progress, setProgress] = useState(0);
  const [isLiked, setIsLiked] = useState(false);
  const [likeCount, setLikeCount] = useState(8600);
  const [isVideoReady, setIsVideoReady] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [videoError, setVideoError] = useState(false);
  const videoRef = useRef(null);
  const progressInterval = useRef(null);

  // console.log('video item pelam', videoItem);

  // Clean video URL (remove /public/ if present)
  const getCleanVideoUrl = (url) => {
    if (!url) return null;
    let cleanUrl = url.replace('/public/uploads/videos/', '/uploads/videos/');
    cleanUrl = cleanUrl.replace('/public/', '/');
    return cleanUrl;
  };

  // Handle video playback when modal opens
  useEffect(() => {
    if (isOpen && videoRef.current && videoItem?.custom_video) {
      setIsVideoReady(false);
      setIsPlaying(false);
      setProgress(0);
      setVideoError(false);
      
      const videoElement = videoRef.current;
      const cleanUrl = getCleanVideoUrl(videoItem.custom_video);
      
      // console.log('Loading video from:', cleanUrl);
      
      videoElement.src = cleanUrl;
      videoElement.load();
      
      const playPromise = videoElement.play();
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            setIsPlaying(true);
            setIsVideoReady(true);
          })
          .catch((error) => {
            console.log("Play was prevented:", error);
            setIsPlaying(false);
            setIsVideoReady(true);
          });
      }
    }
    
    const handleEsc = (e) => {
      if (e.key === "Escape") handleClose();
    };
    window.addEventListener("keydown", handleEsc);
    
    return () => {
      window.removeEventListener("keydown", handleEsc);
      if (progressInterval.current) clearInterval(progressInterval.current);
      if (videoRef.current) videoRef.current.pause();
    };
  }, [isOpen, videoItem]);

  // Update progress bar
  const updateProgress = () => {
    if (progressInterval.current) clearInterval(progressInterval.current);
    
    progressInterval.current = setInterval(() => {
      if (videoRef.current?.duration && !isNaN(videoRef.current.duration)) {
        const value = (videoRef.current.currentTime / videoRef.current.duration) * 100;
        setProgress(isNaN(value) ? 0 : value);
      }
    }, 100);
  };

  const handleProgressChange = (e) => {
    const newProgress = parseFloat(e.target.value);
    if (!isNaN(newProgress) && videoRef.current?.duration) {
      setProgress(newProgress);
      const newTime = (newProgress / 100) * videoRef.current.duration;
      if (!isNaN(newTime)) videoRef.current.currentTime = newTime;
    }
  };

  const handleManualPlay = () => {
    if (videoRef.current && !videoError) {
      const playPromise = videoRef.current.play();
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            setIsPlaying(true);
            updateProgress();
          })
          .catch((error) => {
            console.log("Manual play failed:", error);
            setVideoError(true);
          });
      }
    }
  };

  const handleClose = () => {
    if (videoRef.current) {
      videoRef.current.pause();
      videoRef.current.src = "";
    }
    if (progressInterval.current) clearInterval(progressInterval.current);
    dispatch(closeVideoModal());
    setProgress(0);
    setIsVideoReady(false);
    setIsPlaying(false);
    setVideoError(false);
  };

  const toggleMute = () => {
    if (videoRef.current) {
      videoRef.current.muted = !isMuted;
      setIsMuted(!isMuted);
    }
  };

  const handleLike = () => {
    if (!isLiked) {
      setLikeCount(likeCount + 1);
      setIsLiked(true);
    } else {
      setLikeCount(likeCount - 1);
      setIsLiked(false);
    }
  };

  // const handleComment = () => {
  //   console.log("Comment clicked for video:", videoItem?.name);
  //   alert("Comment feature coming soon!");
  // };

  const handleShare = async () => {
    const shareData = {
      title: videoItem?.name,
      text: `Check out ${videoItem?.name}`,
      url: `${window.location.origin}/product/${videoItem?.slug}`,
    };
    if (navigator.share) {
      try { await navigator.share(shareData); } 
      catch (err) { console.log("Error sharing:", err); }
    } else {
      navigator.clipboard.writeText(shareData.url);
      alert("Link copied to clipboard!");
    }
  };

  const formatPrice = tkText;

  if (!isOpen || !videoItem) return null;

  const videoUrl = getCleanVideoUrl(videoItem.custom_video);

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 z-99998 bg-ink/60 backdrop-blur-[3px]"
        onClick={handleClose}
      />

      {/* Modal Popup */}
      <div className="fixed inset-0 z-99999 flex items-center justify-center p-4">
        <div
          className="relative overflow-hidden rounded-2xl bg-ink shadow-e4"
          style={{
            width: "700px",
            maxWidth: "90vw",
            maxHeight: "100vh",
          }}
          onClick={(e) => e.stopPropagation()}
        >
          {/* Close Button */}
          <button
            onClick={handleClose}
            className="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition-colors hover:bg-black/60"
          >
            ✕
          </button>

          {/* Video Container */}
          <div className="relative w-full bg-ink">
            {videoError ? (
              <div className="flex flex-col items-center justify-center p-8 text-white min-h-[400px]">
                <p className="text-center mb-4">Unable to play this video</p>
                <a
                  href={videoUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="rounded-md bg-white px-4 py-2 text-[0.875rem] font-semibold text-ink transition-transform hover:scale-105"
                >
                  Open Video in New Tab
                </a>
              </div>
            ) : (
              <>
                <video
                  ref={videoRef}
                  muted={isMuted}
                  loop
                  playsInline
                  className="w-full"
                  style={{
                    maxHeight: "80vh",
                    objectFit: "contain",
                    cursor: "pointer",
                  }}
                  onTimeUpdate={updateProgress}
                  onCanPlay={() => setIsVideoReady(true)}
                  preload="auto"
                  onClick={handleManualPlay}
                >
                  <source src={videoUrl} type="video/mp4" />
                  Your browser does not support the video tag.
                </video>

                {/* Play Button Overlay */}
                {!isPlaying && isVideoReady && (
                  <div
                    className="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/25"
                    onClick={handleManualPlay}
                  >
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white/90 shadow-e3 backdrop-blur-md transition-transform hover:scale-110">
                      <Play size={24} className="ml-1 text-ink" fill="currentColor" aria-hidden="true" />
                    </div>
                  </div>
                )}

                {/* Loading Indicator */}
                {!isVideoReady && (
                  <div className="absolute inset-0 flex items-center justify-center bg-black/25">
                    <div className="h-8 w-8 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                  </div>
                )}

                {/* Progress Bar */}
                {isVideoReady && (
                  <div className="absolute top-0 left-0 right-0 px-3 pb-3">
                    <input
                      type="range"
                      value={progress || 0}
                      onChange={handleProgressChange}
                      min="0"
                      max="100"
                      step="0.1"
                      className="w-full progress-bar"
                      style={{
                        background: `linear-gradient(to right, white 0%, white ${progress || 0}%, #444 ${progress || 0}%, #444 100%)`,
                      }}
                    />
                  </div>
                )}

                {/* Product Info */}
                <div className="p-4 absolute bottom-8 left-4 rounded-2xl right-0 bg-linear-to-t from-black/70 to-transparent w-80">
                  <Link
                    href={`/product/${videoItem?.slug}`}
                    className="block"
                    onClick={handleClose}
                  >
                    <div className="flex items-center gap-3 mb-3">
                      <img
                        src={videoItem?.thumbnail_image}
                        alt={videoItem?.name}
                        className="w-12 h-16 object-cover rounded"
                      />
                      <div className="flex-1">
                        <h3 className="type-subhead font-semibold text-white">
                          {videoItem?.name}
                        </h3>
                        <p className="type-subhead tabular mt-1 font-semibold text-white/90">
                          {formatPrice(videoItem?.main_price)}
                        </p>
                      </div>
                    </div>
                    <button className="w-full rounded-md bg-white px-4 py-2.5 text-[0.875rem] font-semibold text-ink transition-transform hover:scale-[1.02] active:scale-[0.98]">
                      View Product 🔗
                    </button>
                  </Link>
                </div>
              </>
            )}
          </div>

          {/* Social Buttons */}
          <div className="absolute right-3 bottom-20 flex flex-col gap-2">
            {/* <button
              onClick={handleLike}
              className="flex h-9 w-9 flex-col items-center justify-center rounded-full bg-black/40 backdrop-blur-md transition-colors hover:bg-black/60"
            >
              <span className="text-xl">{isLiked ? "❤️" : "🤍"}</span>
              <span className="text-white text-[10px]">
                {likeCount.toLocaleString()}
              </span>
            </button>

            <button
              onClick={handleComment}
              className="w-9 h-9 bg-main/60 rounded-full flex items-center justify-center hover:bg-main/80 transition-colors"
            >
              <span className="text-white text-lg">💬</span>
            </button> */}

            <button
              onClick={handleShare}
              className="w-9 h-9 bg-main/60 rounded-full flex items-center justify-center hover:bg-main/80 transition-colors"
            >
              <span className="text-white text-lg">
               <Share2 size={17} className="text-white" aria-hidden="true" />
              </span>
            </button>

            <button
              onClick={toggleMute}
              className="w-9 h-9 bg-main/60 rounded-full flex items-center justify-center hover:bg-main/80 transition-colors"
              aria-label={isMuted ? "Unmute" : "Mute"}
            >
              {isMuted ? (
                <VolumeX size={17} className="text-white" aria-hidden="true" />
              ) : (
                <Volume2 size={17} className="text-white" aria-hidden="true" />
              )}
            </button>
          </div>
        </div>
      </div>

      <style jsx>{`
        .progress-bar {
          -webkit-appearance: none;
          width: 100%;
          height: 3px;
          border-radius: 5px;
          outline: none;
        }
        .progress-bar::-webkit-slider-thumb {
          -webkit-appearance: none;
          width: 12px;
          height: 12px;
          border-radius: 50%;
          background: white;
          cursor: pointer;
        }
        .progress-bar::-webkit-slider-thumb:hover {
          transform: scale(1.2);
        }

        @keyframes spin {
          from {
            transform: rotate(0deg);
          }
          to {
            transform: rotate(360deg);
          }
        }
        .animate-spin {
          animation: spin 1s linear infinite;
        }
      `}</style>
    </>
  );
}