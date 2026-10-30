"use client";
import { useState } from "react";
import Image from "next/image";
import seller_logo from "@/public/seller.png";
import Breadcrumb from "@/app/components/layout/Breadcrumb";

export default function BecomeSeller() {
  const [otp, setOtp] = useState(["", "", "", "", "", ""]);
  
  // State for image previews
  const [shopLogo, setShopLogo] = useState(null);
  const [nidFront, setNidFront] = useState(null);
  const [nidBack, setNidBack] = useState(null);
  const [tradeLicense, setTradeLicense] = useState(null);
  const [bankInfo, setBankInfo] = useState(null);

  // Handle image upload and preview
  const handleImageUpload = (event, setImage) => {
    const file = event.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        setImage(e.target?.result);
      };
      reader.readAsDataURL(file);
    }
  };

  // Remove image preview
  const removeImage = (setImage) => {
    setImage(null);
  };

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Breadcrumb */}
      <Breadcrumb></Breadcrumb>

      {/* Header Card */}
      <div className="bg-main rounded-2xl p-2 flex flex-col md:flex-row items-center justify-between gap-6 mb-10">
        <div className="flex items-center gap-4">
          <div className="rounded-full p-2">
            <Image
              src={seller_logo}
              alt="Seller"
              className="rounded-full w-30 h-auto object-cover"
            />
          </div>
          <div>
            <h2 className="text-2xl font-bold text-gray-800">
              Build Your Business with Softcommerce!
            </h2>
            <p className="text-gray-500 text-sm">
              Complete a quick registration and start selling today.
            </p>
          </div>
        </div>
        <div className="grid grid-cols-3 md:flex md:flex-row gap-8 text-center text-sm">
          <div className="flex flex-col justify-center items-center">
            <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M18.3169 9.4206C15.8396 9.4206 13.8236 11.4366 13.8236 13.9139C13.8236 16.3912 15.8396 18.4072 18.3166 18.4072C20.7937 18.4072 22.8099 16.3912 22.8099 13.9139C22.8099 11.4366 20.7941 9.4206 18.3169 9.4206ZM19.9851 14.997H19.3997V15.5824C19.3997 16.1788 18.9169 16.6655 18.3166 16.6655C17.7164 16.6655 17.2337 16.1824 17.2337 15.5824V14.9968H16.6481C16.0517 14.9968 15.5653 14.5139 15.5653 13.9139C15.5653 13.3139 16.0481 12.8308 16.6481 12.8308H17.2337V12.2452C17.2337 11.649 17.7166 11.1623 18.3166 11.1623C18.9166 11.1623 19.3997 11.6454 19.3997 12.2454V12.831H19.9853C20.5815 12.831 21.0682 13.3139 21.0682 13.9139C21.0682 14.5139 20.5853 14.997 19.9851 14.997ZM9.33414 12.5756C8.3475 12.5756 7.36062 12.1984 6.57438 11.4476C5.11206 10.048 3.75582 7.55964 4.02702 5.15772C4.09782 4.52892 4.2591 3.69924 4.8879 2.85804C5.9535 1.431 7.5651 0.605403 9.30654 0.601562C11.0835 0.601562 12.6913 1.41516 13.7801 2.8542C14.413 3.70332 14.5743 4.53276 14.6451 5.15772V5.1774C14.8769 7.61076 13.5325 10.0835 12.0977 11.4515C11.3115 12.2022 10.3246 12.5756 9.33798 12.5756H9.33414Z"
                fill="var(--brand)"
              />
              <path
                d="M12.5448 13.9112V13.9067C12.545 13.6537 12.3602 13.4367 12.108 13.4195C11.9781 13.4106 11.8479 13.4062 11.7177 13.4063H6.95014C5.80342 13.4046 4.68138 13.7392 3.72286 14.3687C2.13502 15.4115 1.1875 17.2556 1.1875 19.3007V20.0144C1.1875 21.8804 2.70598 23.3989 4.57582 23.3989H14.0995C15.9631 23.3989 17.4878 21.8742 17.4878 20.0106C17.4878 19.7891 17.3342 19.5954 17.1175 19.5493C14.5113 18.9937 12.5479 16.6794 12.5479 13.9076L12.5448 13.9112Z"
                fill="var(--brand)"
              />
            </svg>

            <p className="font-medium">Register</p>
            <p className="text-gray-500">Sign up & submit details</p>
          </div>
          <div className="flex flex-col justify-center items-center">
            <svg
              width="17"
              height="21"
              viewBox="0 0 17 21"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M15.8288 3.4475L12.9325 0.77C12.4075 0.27125 11.7163 0 10.99 0H2.86125C1.28625 0 0 1.28625 0 2.86125V18.1388C0 19.7137 1.28625 21 2.86125 21H13.8775C15.4525 21 16.7388 19.7137 16.7388 18.1388V5.5475C16.7388 4.75125 16.4063 3.99 15.8288 3.4475ZM5.5125 7.1925C7.87763 4.8265 7.79625 4.89737 7.88375 4.83875C8.02589 4.74259 8.19339 4.69082 8.365 4.69H8.37375C8.60125 4.69 8.82 4.78625 8.98625 4.94375L11.2263 7.1925C11.3899 7.35611 11.4818 7.578 11.4818 7.80938C11.4818 8.04075 11.3899 8.26264 11.2263 8.42625C11.0626 8.58986 10.8407 8.68177 10.6094 8.68177C10.378 8.68177 10.1561 8.58986 9.9925 8.42625L9.24875 7.6825V10.7713C9.24875 11.2613 8.855 11.6462 8.37375 11.6462C7.88375 11.6462 7.49875 11.2613 7.49875 10.7713V7.6825L6.74625 8.435C6.58197 8.59734 6.36033 8.68837 6.12937 8.68837C5.89842 8.68837 5.67678 8.59734 5.5125 8.435C5.17125 8.09375 5.17125 7.53375 5.5125 7.1925ZM11.76 16.31H4.97875C4.4975 16.31 4.10375 15.9162 4.10375 15.435C4.10375 14.9537 4.4975 14.56 4.97875 14.56H11.76C12.2413 14.56 12.635 14.9537 12.635 15.435C12.635 15.9162 12.2413 16.31 11.76 16.31Z"
                fill="var(--brand)"
              />
            </svg>

            <p className="font-medium">Upload</p>
            <p className="text-gray-500">Add your products</p>
          </div>
          <div className="flex flex-col justify-center items-center">
            <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M16.0215 5.71903C16.2961 5.99363 16.7413 5.99363 17.0159 5.71903C17.2905 5.44449 17.2905 4.99927 17.0159 4.72467L16.71 4.41872H20.3343V8.98748C20.3689 9.91941 21.7063 9.9187 21.7406 8.98748V3.71564C21.7406 3.32733 21.4258 3.01252 21.0375 3.01252H16.71L17.0159 2.70656C17.2905 2.43202 17.2905 1.9868 17.0159 1.7122C16.7413 1.43761 16.2961 1.43761 16.0215 1.7122L14.5153 3.21844C14.2407 3.49298 14.2407 3.93825 14.5153 4.2128L16.0215 5.71903ZM6.35156 0C2.8493 0 0 2.8493 0 6.35156C0.319312 14.7662 12.3851 14.7639 12.7031 6.35147C12.7031 2.8493 9.85383 0 6.35156 0ZM7.05469 9.38695V9.55233C7.02009 10.4843 5.6827 10.4835 5.64844 9.55233V9.38799C5.32442 9.27947 5.03016 9.0969 4.78903 8.85478C4.51463 8.58005 4.51486 8.13483 4.78959 7.86042C5.06437 7.58602 5.5095 7.58625 5.78395 7.86099C6.23423 8.32303 7.04086 8.05908 7.14258 7.43227C6.93211 7.29881 6.4342 7.12509 6.11995 7.01545C5.24752 6.71105 4.1422 6.34852 4.1422 5.41013C4.1422 4.4377 4.77398 3.61055 5.64844 3.31613V3.15075C5.68303 2.21883 7.02042 2.21953 7.05469 3.15075V3.31509C7.37871 3.42361 7.67297 3.60617 7.91409 3.8483C8.1885 4.12303 8.18827 4.56825 7.91353 4.84266C7.63875 5.11706 7.19362 5.11683 6.91917 4.84209C6.46889 4.38005 5.66227 4.644 5.56055 5.27081C5.77102 5.40427 6.26892 5.57798 6.58317 5.68763C7.45561 5.99203 8.56092 6.35456 8.56092 7.29295C8.56092 8.26542 7.92914 9.09253 7.05469 9.38695ZM7.97845 18.281C7.70386 18.0064 7.25869 18.0064 6.98409 18.281C6.7095 18.5555 6.7095 19.0007 6.98409 19.2753L7.29005 19.5813H3.66562V15.0125C3.63103 14.0806 2.29364 14.0813 2.25938 15.0125V20.2844C2.25938 20.6727 2.57419 20.9875 2.9625 20.9875H7.29L6.98405 21.2935C6.70945 21.568 6.70945 22.0133 6.98405 22.2878C7.25864 22.5624 7.70381 22.5624 7.97841 22.2878L9.48464 20.7816C9.75923 20.5071 9.75923 20.0618 9.48464 19.7873L7.97845 18.281ZM18 9.64355C17.8931 9.58183 17.7719 9.54933 17.6484 9.54933C17.525 9.54933 17.4038 9.58183 17.2969 9.64355L12 12.7016L17.6484 15.9628L23.2969 12.7017L18 9.64355ZM18.3516 17.1806V23.7028L23.6484 20.6447C23.7553 20.583 23.8441 20.4943 23.9058 20.3874C23.9675 20.2805 24 20.1593 24 20.0358V13.9195L18.3516 17.1806ZM11.2969 20.0358C11.2969 20.1593 11.3294 20.2805 11.3911 20.3874C11.4528 20.4943 11.5415 20.583 11.6484 20.6447L16.9453 23.7028V17.1806L11.2969 13.9195V20.0358Z"
                fill="var(--brand)"
              />
            </svg>

            <p className="font-medium">Sell</p>
            <p className="text-gray-500">Start growing your business</p>
          </div>
        </div>
      </div>

      {/* Form Sections */}
      <div className="grid md:grid-cols-3 gap-10">
        {/* Personal Info */}
        <div>
          <h3 className="text-brand font-semibold mb-4">
            01&nbsp; Personal Info
          </h3>

          <label className="block text-sm mb-1 text-gray-700">Full Name</label>
          <input
            type="text"
            placeholder="Enter First & Last Name"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <label className="block text-sm mb-1 text-gray-700">
            Your Number
          </label>
          <div className="relative mb-4">
            <input
              type="text"
              placeholder="Enter Your Number"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
            />
            <small className="flex justify-end py-2 text-[10px] text-red-500">
              Send OTP via SMS
            </small>
          </div>

          <label className="block text-sm mb-1 text-gray-700">
            Verify Your Number
          </label>
          <div className="flex gap-2 mb-4">
            {otp.map((val, idx) => (
              <input
                key={idx}
                maxLength={1}
                className="w-10 h-10 border border-gray-300 rounded-lg text-center text-sm"
              />
            ))}
          </div>

          <button className="bg-main text-white text-xs px-4 py-2 rounded-lg mb-6">
            SUBMIT OTP
          </button>

          <label className="block text-sm mb-1 text-gray-700">Your Email</label>
          <input
            type="email"
            placeholder="Enter Your Email"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <label className="block text-sm mb-1 text-gray-700">Password</label>
          <div className="relative mb-1">
            <input
              type="password"
              placeholder="Type a Password"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
            />
            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer">
              <svg
                width="16"
                height="10"
                viewBox="0 0 16 10"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M8 0C4.94303 0 2.17081 1.6725 0.125192 4.38908C-0.0417306 4.61164 -0.0417306 4.92257 0.125192 5.14514C2.17081 7.86499 4.94303 9.53749 8 9.53749C11.057 9.53749 13.8292 7.86499 15.8748 5.14841C16.0417 4.92585 16.0417 4.61491 15.8748 4.39235C13.8292 1.6725 11.057 0 8 0ZM8.21929 8.12683C6.19004 8.25447 4.51427 6.58198 4.64191 4.54945C4.74665 2.87368 6.10494 1.51539 7.78071 1.41066C9.80996 1.28301 11.4857 2.95551 11.3581 4.98803C11.2501 6.66053 9.89179 8.01882 8.21929 8.12683ZM8.11783 6.57543C7.02465 6.64416 6.1213 5.74409 6.19331 4.65092C6.24895 3.74757 6.9821 3.01769 7.88545 2.95878C8.97862 2.89005 9.88197 3.79012 9.80996 4.8833C9.75105 5.78992 9.0179 6.51979 8.11783 6.57543Z"
                  fill="var(--ink-tertiary)"
                />
              </svg>
            </span>
          </div>
          <p className="text-[10px] text-gray-400 mb-4">
            Password Must Contain at Least 6 Digits
          </p>

          <label className="block text-sm mb-1 text-gray-700">
            Confirm Password
          </label>
          <div className="relative mb-4">
            <input
              type="password"
              placeholder="Retype Your Password"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
            />
            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer">
              <svg
                width="16"
                height="10"
                viewBox="0 0 16 10"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M8 0C4.94303 0 2.17081 1.6725 0.125192 4.38908C-0.0417306 4.61164 -0.0417306 4.92257 0.125192 5.14514C2.17081 7.86499 4.94303 9.53749 8 9.53749C11.057 9.53749 13.8292 7.86499 15.8748 5.14841C16.0417 4.92585 16.0417 4.61491 15.8748 4.39235C13.8292 1.6725 11.057 0 8 0ZM8.21929 8.12683C6.19004 8.25447 4.51427 6.58198 4.64191 4.54945C4.74665 2.87368 6.10494 1.51539 7.78071 1.41066C9.80996 1.28301 11.4857 2.95551 11.3581 4.98803C11.2501 6.66053 9.89179 8.01882 8.21929 8.12683ZM8.11783 6.57543C7.02465 6.64416 6.1213 5.74409 6.19331 4.65092C6.24895 3.74757 6.9821 3.01769 7.88545 2.95878C8.97862 2.89005 9.88197 3.79012 9.80996 4.8833C9.75105 5.78992 9.0179 6.51979 8.11783 6.57543Z"
                  fill="var(--ink-tertiary)"
                />
              </svg>
            </span>
          </div>
        </div>

        {/* Basic Info */}
        <div>
          <h3 className="text-brand font-semibold mb-4">
            02&nbsp; Basic Info
          </h3>

          <label className="block text-sm mb-2 text-gray-700">
            Add Shop Logo{" "}
            <span className="text-xs text-gray-400">*Maximum Size 8 MB</span>
          </label>
          <div className="w-full mb-4">
            {shopLogo ? (
              <div className="relative border border-gray-300 rounded-xl p-4">
                <Image
                  src={shopLogo}
                  alt="Shop Logo Preview"
                  width={200}
                  height={200}
                  className="w-full h-32 object-contain rounded-lg"
                />
                <button
                  onClick={() => removeImage(setShopLogo)}
                  className="absolute top-2 right-2 bg-main text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
                >
                  ×
                </button>
              </div>
            ) : (
              <label
                htmlFor="imageUpload"
                className="flex items-center justify-center gap-4 w-full border-2 border-dashed border-gray-300 rounded-xl p-6 cursor-pointer hover:border-main hover:bg-purple-50 transition"
              >
                <svg
                  width="40"
                  height="40"
                  viewBox="0 0 32 32"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                  className="text-main-700"
                >
                  <path
                    d="M22.3269 11.2071C22.3287 9.52958 20.9705 8.16822 19.2932 8.16646C17.616 8.16471 16.2549 9.52322 16.2532 11.2008C16.2514 12.8783 17.6097 14.2397 19.2869 14.2415C20.9641 14.2432 22.3252 12.8847 22.3269 11.2071Z"
                    fill="currentColor"
                  />
                  <path
                    d="M19.2578 25.6323C19.2578 29.1505 22.1097 32.003 25.6278 32.003C29.1459 32.003 31.9978 29.1505 31.9978 25.6323C31.9978 22.1142 29.1459 19.2617 25.6278 19.2617C22.1097 19.2617 19.2578 22.1142 19.2578 25.6323ZM26.8147 22.8898V24.4448H28.3691C29.0247 24.4448 29.5566 24.9767 29.5566 25.6323C29.5566 26.288 29.0247 26.8198 28.3691 26.8198H26.8147V28.3748C26.8147 29.0305 26.2828 29.5623 25.6272 29.5623C24.9716 29.5623 24.4397 29.0305 24.4397 28.3748V26.8198H22.8853C22.2297 26.8198 21.6978 26.288 21.6978 25.6323C21.6978 24.9767 22.2297 24.4448 22.8853 24.4448H24.4397V22.8898C24.4397 22.2342 24.9716 21.7023 25.6272 21.7023C26.2828 21.7023 26.8147 22.2342 26.8147 22.8898Z"
                    fill="currentColor"
                  />
                  <path
                    d="M0.56375 21.9181C1.14062 25.185 4.03938 28.0837 7.30625 28.6612L7.31812 28.6631C9.5725 29.0381 11.9575 29.2219 14.6125 29.2244C15.8931 29.2231 17.1106 29.1788 18.2819 29.0919C17.7769 28.0225 17.5112 26.8456 17.5112 25.6281C17.5112 23.4587 18.3556 21.42 19.8894 19.8856C21.0794 18.695 22.5744 17.92 24.1981 17.6325L24.0987 17.4919C22.89 15.7881 20.6075 15.7356 19.52 17.4338C19.3944 17.6287 19.2687 17.8237 19.1419 18.0187C18.4144 19.1388 16.8681 19.135 16.1138 18.0031C14.7269 15.925 13.3581 13.8444 12.0737 11.7794C11.0119 10.0694 8.85312 10.1156 7.65875 11.8469C5.98563 14.285 4.26 16.71 2.58562 19.0956C2.44625 17.6844 2.37687 16.2006 2.375 14.6125C2.37812 12.0925 2.55063 9.83562 2.90313 7.71313C3.09938 6.61188 3.70375 5.50875 4.60625 4.60563C5.50875 3.70313 6.61188 3.09812 7.71313 2.90188C9.835 2.54938 12.0919 2.37625 14.6088 2.37375C17.1275 2.37625 19.3844 2.54938 21.5075 2.90188C22.6087 3.09812 23.7119 3.7025 24.6144 4.60563C25.5169 5.50813 26.1219 6.61188 26.3175 7.71313C26.67 9.83625 26.8431 12.0931 26.8456 14.61C26.8444 15.6431 26.8144 16.6312 26.755 17.585C27.5687 17.6975 28.3544 17.9306 29.0888 18.2769C29.175 17.1069 29.2194 15.89 29.2206 14.61C29.2175 11.9569 29.0337 9.57187 28.6594 7.3175L28.6575 7.30562C28.08 4.03875 25.1819 1.14 21.915 0.5625L21.9031 0.560625C19.6494 0.186875 17.2644 0.003125 14.6094 0C11.9563 0.003125 9.57063 0.186875 7.3175 0.56125L7.30562 0.563125C4.03938 1.14125 1.14062 4.04 0.56375 7.30688L0.561875 7.31875C0.186875 9.5725 0.003125 11.9575 0 14.6138C0.003125 17.2688 0.186875 19.6544 0.56125 21.9069L0.563125 21.9188L0.56375 21.9181Z"
                    fill="currentColor"
                  />
                </svg>
                <p className="text-gray-600 font-medium text-base">
                  <span className="text-main-600 font-semibold">
                    Click to upload
                  </span>{" "}
                  or drag and drop
                  <br />
                  <span className="text-sm text-gray-400">
                    PNG, JPG up to 5MB
                  </span>
                </p>
              </label>
            )}
            <input
              id="imageUpload"
              type="file"
              className="hidden"
              accept="image/*"
              onChange={(e) => handleImageUpload(e, setShopLogo)}
            />
          </div>

          <label className="block text-sm mb-1 text-gray-700">Shop Name</label>
          <input
            type="text"
            placeholder="Enter Your Shop Name"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <label className="block text-sm mb-1 text-gray-700">Address</label>
          <div className="flex gap-2 mb-4">
            <select className="w-1/3 border border-gray-300 rounded-lg px-2 py-2 text-sm text-gray-500">
              <option>Add City</option>
            </select>
            <input
              type="text"
              placeholder="Type House no, Road No..."
              className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div className="w-full">
            <label className="block text-sm mb-2 text-gray-700">Add NID</label>

            <div className="grid grid-cols-2 gap-3 mb-4">
              {/* Front Side Upload */}
              {nidFront ? (
                <div className="relative border border-gray-300 rounded-xl p-4">
                  <Image
                    src={nidFront}
                    alt="NID Front Preview"
                    width={150}
                    height={100}
                    className="w-full h-24 object-contain rounded-lg"
                  />
                  <button
                    onClick={() => removeImage(setNidFront)}
                    className="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
                  >
                    ×
                  </button>
                </div>
              ) : (
                <label
                  htmlFor="nidFront"
                  className="flex items-center justify-center gap-3 w-full border-2 border-dashed border-gray-300 rounded-xl p-6 cursor-pointer hover:border-main hover:bg-purple-50 transition"
                >
                  <svg
                    width="28"
                    height="28"
                    viewBox="0 0 32 32"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    className="text-main"
                  >
                    <path
                      d="M22.3269 11.2071C22.3287 9.52958 20.9705 8.16822 19.2932 8.16646C17.616 8.16471 16.2549 9.52322 16.2532 11.2008C16.2514 12.8783 17.6097 14.2397 19.2869 14.2415C20.9641 14.2432 22.3252 12.8847 22.3269 11.2071Z"
                      fill="currentColor"
                    />
                    <path
                      d="M19.2578 25.6323C19.2578 29.1505 22.1097 32.003 25.6278 32.003C29.1459 32.003 31.9978 29.1505 31.9978 25.6323C31.9978 22.1142 29.1459 19.2617 25.6278 19.2617C22.1097 19.2617 19.2578 22.1142 19.2578 25.6323ZM26.8147 22.8898V24.4448H28.3691C29.0247 24.4448 29.5566 24.9767 29.5566 25.6323C29.5566 26.288 29.0247 26.8198 28.3691 26.8198H26.8147V28.3748C26.8147 29.0305 26.2828 29.5623 25.6272 29.5623C24.9716 29.5623 24.4397 29.0305 24.4397 28.3748V26.8198H22.8853C22.2297 26.8198 21.6978 26.288 21.6978 25.6323C21.6978 24.9767 22.2297 24.4448 22.8853 24.4448H24.4397V22.8898C24.4397 22.2342 24.9716 21.7023 25.6272 21.7023C26.2828 21.7023 26.8147 22.2342 26.8147 22.8898Z"
                      fill="currentColor"
                    />
                    <path
                      d="M0.56375 21.9181C1.14062 25.185 4.03938 28.0837 7.30625 28.6612L7.31812 28.6631C9.5725 29.0381 11.9575 29.2219 14.6125 29.2244C15.8931 29.2231 17.1106 29.1788 18.2819 29.0919C17.7769 28.0225 17.5112 26.8456 17.5112 25.6281C17.5112 23.4587 18.3556 21.42 19.8894 19.8856C21.0794 18.695 22.5744 17.92 24.1981 17.6325L24.0987 17.4919C22.89 15.7881 20.6075 15.7356 19.52 17.4338C19.3944 17.6287 19.2687 17.8237 19.1419 18.0187C18.4144 19.1388 16.8681 19.135 16.1138 18.0031C14.7269 15.925 13.3581 13.8444 12.0737 11.7794C11.0119 10.0694 8.85312 10.1156 7.65875 11.8469C5.98563 14.285 4.26 16.71 2.58562 19.0956C2.44625 17.6844 2.37687 16.2006 2.375 14.6125C2.37812 12.0925 2.55063 9.83562 2.90313 7.71313C3.09938 6.61188 3.70375 5.50875 4.60625 4.60563C5.50875 3.70313 6.61188 3.09812 7.71313 2.90188C9.835 2.54938 12.0919 2.37625 14.6088 2.37375C17.1275 2.37625 19.3844 2.54938 21.5075 2.90188C22.6087 3.09812 23.7119 3.7025 24.6144 4.60563C25.5169 5.50813 26.1219 6.61188 26.3175 7.71313C26.67 9.83625 26.8431 12.0931 26.8456 14.61C26.8444 15.6431 26.8144 16.6312 26.755 17.585C27.5687 17.6975 28.3544 17.9306 29.0888 18.2769C29.175 17.1069 29.2194 15.89 29.2206 14.61C29.2175 11.9569 29.0337 9.57187 28.6594 7.3175L28.6575 7.30562C28.08 4.03875 25.1819 1.14 21.915 0.5625L21.9031 0.560625C19.6494 0.186875 17.2644 0.003125 14.6094 0C11.9563 0.003125 9.57063 0.186875 7.3175 0.56125L7.30562 0.563125C4.03938 1.14125 1.14062 4.04 0.56375 7.30688L0.561875 7.31875C0.186875 9.5725 0.003125 11.9575 0 14.6138C0.003125 17.2688 0.186875 19.6544 0.56125 21.9069L0.563125 21.9188L0.56375 21.9181Z"
                      fill="currentColor"
                    />
                  </svg>
                  <p className="text-gray-600 font-medium text-sm">
                    <span className="text-main font-semibold">+ Front Side</span>
                  </p>
                </label>
              )}
              <input
                id="nidFront"
                type="file"
                className="hidden"
                accept="image/*"
                onChange={(e) => handleImageUpload(e, setNidFront)}
              />

              {/* Back Side Upload */}
              {nidBack ? (
                <div className="relative border border-gray-300 rounded-xl p-4">
                  <Image
                    src={nidBack}
                    alt="NID Back Preview"
                    width={150}
                    height={100}
                    className="w-full h-24 object-contain rounded-lg"
                  />
                  <button
                    onClick={() => removeImage(setNidBack)}
                    className="absolute top-2 right-2 bg-main text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
                  >
                    ×
                  </button>
                </div>
              ) : (
                <label
                  htmlFor="nidBack"
                  className="flex items-center justify-center gap-3 w-full border-2 border-dashed border-gray-300 rounded-xl p-6 cursor-pointer hover:border-main hover:bg-purple-50 transition"
                >
                  <svg
                    width="28"
                    height="28"
                    viewBox="0 0 32 32"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    className="text-main"
                  >
                    <path
                      d="M22.3269 11.2071C22.3287 9.52958 20.9705 8.16822 19.2932 8.16646C17.616 8.16471 16.2549 9.52322 16.2532 11.2008C16.2514 12.8783 17.6097 14.2397 19.2869 14.2415C20.9641 14.2432 22.3252 12.8847 22.3269 11.2071Z"
                      fill="currentColor"
                    />
                    <path
                      d="M19.2578 25.6323C19.2578 29.1505 22.1097 32.003 25.6278 32.003C29.1459 32.003 31.9978 29.1505 31.9978 25.6323C31.9978 22.1142 29.1459 19.2617 25.6278 19.2617C22.1097 19.2617 19.2578 22.1142 19.2578 25.6323ZM26.8147 22.8898V24.4448H28.3691C29.0247 24.4448 29.5566 24.9767 29.5566 25.6323C29.5566 26.288 29.0247 26.8198 28.3691 26.8198H26.8147V28.3748C26.8147 29.0305 26.2828 29.5623 25.6272 29.5623C24.9716 29.5623 24.4397 29.0305 24.4397 28.3748V26.8198H22.8853C22.2297 26.8198 21.6978 26.288 21.6978 25.6323C21.6978 24.9767 22.2297 24.4448 22.8853 24.4448H24.4397V22.8898C24.4397 22.2342 24.9716 21.7023 25.6272 21.7023C26.2828 21.7023 26.8147 22.2342 26.8147 22.8898Z"
                      fill="currentColor"
                    />
                    <path
                      d="M0.56375 21.9181C1.14062 25.185 4.03938 28.0837 7.30625 28.6612L7.31812 28.6631C9.5725 29.0381 11.9575 29.2219 14.6125 29.2244C15.8931 29.2231 17.1106 29.1788 18.2819 29.0919C17.7769 28.0225 17.5112 26.8456 17.5112 25.6281C17.5112 23.4587 18.3556 21.42 19.8894 19.8856C21.0794 18.695 22.5744 17.92 24.1981 17.6325L24.0987 17.4919C22.89 15.7881 20.6075 15.7356 19.52 17.4338C19.3944 17.6287 19.2687 17.8237 19.1419 18.0187C18.4144 19.1388 16.8681 19.135 16.1138 18.0031C14.7269 15.925 13.3581 13.8444 12.0737 11.7794C11.0119 10.0694 8.85312 10.1156 7.65875 11.8469C5.98563 14.285 4.26 16.71 2.58562 19.0956C2.44625 17.6844 2.37687 16.2006 2.375 14.6125C2.37812 12.0925 2.55063 9.83562 2.90313 7.71313C3.09938 6.61188 3.70375 5.50875 4.60625 4.60563C5.50875 3.70313 6.61188 3.09812 7.71313 2.90188C9.835 2.54938 12.0919 2.37625 14.6088 2.37375C17.1275 2.37625 19.3844 2.54938 21.5075 2.90188C22.6087 3.09812 23.7119 3.7025 24.6144 4.60563C25.5169 5.50813 26.1219 6.61188 26.3175 7.71313C26.67 9.83625 26.8431 12.0931 26.8456 14.61C26.8444 15.6431 26.8144 16.6312 26.755 17.585C27.5687 17.6975 28.3544 17.9306 29.0888 18.2769C29.175 17.1069 29.2194 15.89 29.2206 14.61C29.2175 11.9569 29.0337 9.57187 28.6594 7.3175L28.6575 7.30562C28.08 4.03875 25.1819 1.14 21.915 0.5625L21.9031 0.560625C19.6494 0.186875 17.2644 0.003125 14.6094 0C11.9563 0.003125 9.57063 0.186875 7.3175 0.56125L7.30562 0.563125C4.03938 1.14125 1.14062 4.04 0.56375 7.30688L0.561875 7.31875C0.186875 9.5725 0.003125 11.9575 0 14.6138C0.003125 17.2688 0.186875 19.6544 0.56125 21.9069L0.563125 21.9188L0.56375 21.9181Z"
                      fill="currentColor"
                    />
                  </svg>
                  <p className="text-gray-600 font-medium text-sm">
                    <span className="text-main font-semibold">+ Back Side</span>
                  </p>
                </label>
              )}
              <input
                id="nidBack"
                type="file"
                className="hidden"
                accept="image/*"
                onChange={(e) => handleImageUpload(e, setNidBack)}
              />
            </div>
          </div>

          <label className="block text-sm mb-2 text-gray-700">
            Add Trade License
          </label>

          {tradeLicense ? (
            <div className="relative border border-gray-300 rounded-xl p-4 mb-4">
              <Image
                src={tradeLicense}
                alt="Trade License Preview"
                width={200}
                height={150}
                className="w-full h-32 object-contain rounded-lg"
              />
              <button
                onClick={() => removeImage(setTradeLicense)}
                className="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
              >
                ×
              </button>
            </div>
          ) : (
            <label
              htmlFor="tradeLicense"
              className="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-main cursor-pointer transition block mb-4"
            >
              <p className="text-sm text-gray-400">+ Add Image</p>
            </label>
          )}

          <input
            id="tradeLicense"
            type="file"
            className="hidden"
            accept="image/*"
            onChange={(e) => handleImageUpload(e, setTradeLicense)}
          />
        </div>

        {/* Bank Details */}
        <div>
          <h3 className="text-brand font-semibold mb-4">
            03 Add Bank Details
          </h3>

          <label className="block text-sm mb-1 text-gray-700">
            Account Holder Name
          </label>
          <input
            type="text"
            placeholder="Enter Your Account Holder Name"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <label className="block text-sm mb-1 text-gray-700">
            Account Number
          </label>
          <input
            type="text"
            placeholder="Enter Your Account Number"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <div className="flex gap-2 mb-4">
            <div className="flex-1">
              <label className="block text-sm mb-1 text-gray-700">
                Select Bank
              </label>
              <select className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-500">
                <option>Select Bank</option>
              </select>
            </div>
            <div className="flex-1">
              <label className="block text-sm mb-1 text-gray-700">
                Select Branch
              </label>
              <select className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-500">
                <option>Select Branch</option>
              </select>
            </div>
          </div>

          <label className="block text-sm mb-1 text-gray-700">
            Add Bank Code
          </label>
          <input
            type="text"
            placeholder="Enter Bank Code"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-sm"
          />

          <label className="block text-sm mb-2 text-gray-700">
            Bank Information document
            <span className="ml-1 text-gray-400 cursor-pointer text-xs">ⓘ</span>
          </label>

          {bankInfo ? (
            <div className="relative border border-gray-300 rounded-xl p-4 mb-4">
              <Image
                src={bankInfo}
                alt="Bank Information Preview"
                width={200}
                height={150}
                className="w-full h-32 object-contain rounded-lg"
              />
              <button
                onClick={() => removeImage(setBankInfo)}
                className="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
              >
                ×
              </button>
            </div>
          ) : (
            <label
              htmlFor="bankInfo"
              className="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-main cursor-pointer transition block mb-4"
            >
              <p className="text-sm text-gray-400">+ Add Image</p>
            </label>
          )}

          <input
            id="bankInfo"
            type="file"
            className="hidden"
            accept="image/*"
            onChange={(e) => handleImageUpload(e, setBankInfo)}
          />
        </div>
      </div>

      {/* Submit Button */}
      <div className="mt-10">
        <button className="flex items-center justify-between gap-2 bg-main text-white font-semibold py-2 px-2 rounded-lg w-full md:w-[50%] mx-auto">
          SUBMIT FOR VERIFICATION{" "}
          <span>
            {" "}
            <svg
              width="30"
              height="30"
              viewBox="0 0 30 30"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <rect
                x="1.31134e-06"
                y="30"
                width="30"
                height="30"
                rx="4"
                transform="rotate(-90 1.31134e-06 30)"
                fill="white"
              />
              <path
                d="M22.5 8.75C22.5 9.125 22.375 9.375 22.125 9.625L9.625 22.125C9.125 22.625 8.375 22.625 7.875 22.125C7.375 21.625 7.375 20.875 7.875 20.375L20.375 7.875C20.875 7.375 21.625 7.375 22.125 7.875C22.375 8.125 22.5 8.375 22.5 8.75Z"
                fill="var(--ink)"
              />
              <path
                d="M22.5 8.75L22.5 20C22.5 20.75 22 21.25 21.25 21.25C20.5 21.25 20 20.75 20 20L20 10L10 10C9.25 10 8.75 9.5 8.75 8.75C8.75 8 9.25 7.5 10 7.5L21.25 7.5C22 7.5 22.5 8 22.5 8.75Z"
                fill="var(--ink)"
              />
            </svg>
          </span>
        </button>
      </div>
    </div>
  );
}