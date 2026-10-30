import { cx, imageSrc, videoSource } from "../core/values.js";
import { FunnelImage, rootWith } from "../render/parts.jsx";
import { VideoPlayer } from "./client.jsx";

export function ImageGallery({ props, ctx, rootProps }) {
  const images = (Array.isArray(props.images) ? props.images : []).filter((item) => imageSrc(item?.image));
  const carousel = props.layout === "carousel";

  if (images.length === 0) {
    return (
      <div {...rootWith(rootProps, "fn-gallery")}>
        {ctx.mode === "editor" ? (
          <div className="flex min-h-[140px] items-center justify-center rounded-[8px] bg-canvas-sunken text-[13px] text-ink-tertiary">Add images to the gallery</div>
        ) : null}
      </div>
    );
  }

  return (
    <div {...rootWith(rootProps, "fn-gallery")}>
      <div
        className={cx(
          "fn-grid",
          carousel
            ? "flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 [scrollbar-width:thin]"
            : "grid grid-cols-2 gap-3 @3xl:grid-cols-3",
        )}
      >
        {images.map((item, index) => {
          const picture = (
            <FunnelImage
              image={item.image}
              ctx={ctx}
              className="block aspect-square h-full w-full rounded-[8px] object-cover"
              eager={index === 0}
            />
          );
          return (
            <figure key={index} className={cx("m-0", carousel && "w-[78%] shrink-0 snap-center @3xl:w-[40%]")}>
              {props.lightbox && ctx.mode !== "editor" ? (
                <a href={imageSrc(item.image)} target="_blank" rel="noopener noreferrer">{picture}</a>
              ) : (
                picture
              )}
              {item.caption ? <figcaption className="mt-1.5 text-center text-[13px] opacity-75">{item.caption}</figcaption> : null}
            </figure>
          );
        })}
      </div>
    </div>
  );
}

export function VideoBlock({ props, ctx, rootProps }) {
  return (
    <div {...rootWith(rootProps, "fn-video")}>
      <VideoPlayer source={videoSource(props.provider, props.url)} poster={props.poster} editing={ctx.mode === "editor"} />
    </div>
  );
}
