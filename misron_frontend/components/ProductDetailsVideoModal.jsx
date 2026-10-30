"use client";
import { useEffect, useRef, useState } from "react";
import { useSelector, useDispatch } from "react-redux";
import { closeVideoModal } from "@/store/slices/videoModalSlice";
import Link from "next/link";
import { Play, Share2, Volume2, VolumeX, X } from "lucide-react";
import { tkText } from "@/utils/currency";

export default function ProductDetailsVideoModal() {
  const { isOpen, videoItem } = useSelector((state) => state.videoModal);
  const dispatch = useDispatch();
  const [isMuted, setIsMuted] = useState(true);
  const [progress, setProgress] = useState(0);
  const [isLiked, setIsLiked] = useState(false);
  const [likeCount, setLikeCount] = useState(8600);
  const [isVideoReady, setIsVideoReady] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const videoRef = useRef(null);
  const progressInterval = useRef(null);

  // Handle video playback when modal opens
  useEffect(() => {
    if (isOpen && videoRef.current && videoItem) {
      // Reset states
      setIsVideoReady(false);
      setIsPlaying(false);
      setProgress(0);
      
      // Load and play video with proper promise handling
      const videoElement = videoRef.current;
      
      // Set source and load
      if (videoElement.src !== videoItem?.custom_video) {
        videoElement.src = videoItem?.custom_video;
        videoElement.load();
      }
      
      // Handle play with promise
      const playPromise = videoElement.play();
      
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            // Video started playing successfully
            setIsPlaying(true);
            setIsVideoReady(true);
          })
          .catch((error) => {
            console.log("Play was prevented:", error);
            // Auto-play was prevented - show play button overlay
            setIsPlaying(false);
            setIsVideoReady(true); // Still show video thumbnail
          });
      }
    }

    // Handle ESC key
    const handleEsc = (e) => {
      if (e.key === "Escape") {
        handleClose();
      }
    };
    window.addEventListener("keydown", handleEsc);
    
    return () => {
      window.removeEventListener("keydown", handleEsc);
      if (progressInterval.current) {
        clearInterval(progressInterval.current);
      }
      // Pause video when component unmounts or modal closes
      if (videoRef.current) {
        videoRef.current.pause();
      }
    };
  }, [isOpen, videoItem]);

  // Update progress bar
  const updateProgress = () => {
    if (progressInterval.current) {
      clearInterval(progressInterval.current);
    }
    
    progressInterval.current = setInterval(() => {
      if (videoRef.current && videoRef.current.duration && !isNaN(videoRef.current.duration)) {
        const value = (videoRef.current.currentTime / videoRef.current.duration) * 100;
        setProgress(isNaN(value) ? 0 : value);
      }
    }, 100);
  };

  // Handle progress bar change
  const handleProgressChange = (e) => {
    const newProgress = parseFloat(e.target.value);
    if (!isNaN(newProgress) && videoRef.current && videoRef.current.duration) {
      setProgress(newProgress);
      const newTime = (newProgress / 100) * videoRef.current.duration;
      if (!isNaN(newTime)) {
        videoRef.current.currentTime = newTime;
      }
    }
  };

  // Manual play function (for when auto-play is blocked)
  const handleManualPlay = () => {
    if (videoRef.current) {
      const playPromise = videoRef.current.play();
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            setIsPlaying(true);
          })
          .catch((error) => {
            console.log("Manual play failed:", error);
          });
      }
    }
  };

  // Close modal
  const handleClose = () => {
    if (videoRef.current) {
      videoRef.current.pause();
      // Clear source to stop any ongoing loading
      videoRef.current.src = "";
    }
    if (progressInterval.current) {
      clearInterval(progressInterval.current);
    }
    dispatch(closeVideoModal());
    setProgress(0);
    setIsVideoReady(false);
    setIsPlaying(false);
  };

  // Toggle mute/unmute
  const toggleMute = () => {
    if (videoRef.current) {
      videoRef.current.muted = !isMuted;
      setIsMuted(!isMuted);
    }
  };

  // Handle like button
  const handleLike = () => {
    if (!isLiked) {
      setLikeCount(likeCount + 1);
      setIsLiked(true);
    } else {
      setLikeCount(likeCount - 1);
      setIsLiked(false);
    }
  };

  // Handle comment button - Fixed: Added this function
  const handleComment = () => {
    // You can implement comment functionality here
    // For example: open a comment section or show a comment input
    console.log("Comment clicked for video:", videoItem?.name);
    alert("Comment feature coming soon!");
  };

  // Handle share
  const handleShare = async () => {
    const shareData = {
      title: videoItem?.name,
      text: `Check out ${videoItem?.name}`,
      url: `${window.location.origin}/product/${videoItem?.slug}`,
    };
    
    if (navigator.share) {
      try {
        await navigator.share(shareData);
      } catch (err) {
        console.log("Error sharing:", err);
      }
    } else {
      navigator.clipboard.writeText(shareData.url);
      alert("Link copied to clipboard!");
    }
  };

  // Format price
  const formatPrice = tkText;

  if (!isOpen || !videoItem) return null;

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
            <video
              ref={videoRef}
              muted={isMuted}
              loop
              playsInline
              className="w-full"
              style={{ maxHeight: "80vh", objectFit: "contain", cursor: "pointer" }}
              onTimeUpdate={updateProgress}
              onCanPlay={() => setIsVideoReady(true)}
              preload="auto"
              onClick={handleManualPlay}
            >
              <source src={videoItem?.custom_video} type="video/mp4" />
              Your browser does not support the video tag.
            </video>

            {/* Play Button Overlay (for when auto-play is blocked) */}
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
            >
              {isMuted ? <VolumeX size={17} className="text-white" aria-hidden="true" /> : <Volume2 size={17} className="text-white" aria-hidden="true" />}
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