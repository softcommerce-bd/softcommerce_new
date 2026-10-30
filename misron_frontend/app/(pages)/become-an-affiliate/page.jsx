"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import Breadcrumb from "@/app/components/layout/Breadcrumb";
import {
  User as FiUser,
  Mail as FiMail,
  Lock as FiLock,
  Upload as FiUpload,
  Eye as FiEye,
  EyeOff as FiEyeOff,
  AlertCircle,
} from "lucide-react";
import { Button, PageSpinner } from "@/app/components/ui";
import { getAffiliateInfo, applyForAffiliate } from "@/app/services/affiliateService";
import { useReduxAuth } from "@/store/hooks/useReduxAuth";
import { toast } from "react-hot-toast";

const BecomeAnAffiliate = () => {
  const router = useRouter();
  const { user, isAuthenticated } = useReduxAuth();
  
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
  
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverErrors, setServerErrors] = useState([]);
  const [verificationFiles, setVerificationFiles] = useState({});
  const [fileNames, setFileNames] = useState({});
  const [verificationData, setVerificationData] = useState({});
  const [verificationForm, setVerificationForm] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [affiliateInfo, setAffiliateInfo] = useState(null);

  useEffect(() => {
    fetchAffiliateInfo();
  }, []);

  useEffect(() => {
    if (isAuthenticated && user?.affiliate_user?.status === 1) {
      router.push("/affiliate/dashboard");
    }
  }, [isAuthenticated, user]);

  const fetchAffiliateInfo = async () => {
    try {
      const response = await getAffiliateInfo();
      if (response.result) {
        setAffiliateInfo(response.data);
        
        if (response.data.verification_form && Array.isArray(response.data.verification_form)) {
          setVerificationForm(response.data.verification_form);
        } else if (response.data.verification_form) {
          const parsedForm = typeof response.data.verification_form === 'string' 
            ? JSON.parse(response.data.verification_form) 
            : response.data.verification_form;
          setVerificationForm(parsedForm);
        }
      }
    } catch (error) {
      console.error("Failed to fetch affiliate info:", error);
      setServerErrors(["Failed to load affiliate information. Please try again later."]);
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
    if (errors[name]) {
      setErrors((prev) => ({
        ...prev,
        [name]: "",
      }));
    }
  };

  const handleVerificationInputChange = (e) => {
    const { name, value, type, files, multiple } = e.target;
    
    if (type === "file") {
      const file = files[0];
      if (file) {
        setVerificationFiles((prev) => ({
          ...prev,
          [name]: file,
        }));
        setFileNames((prev) => ({
          ...prev,
          [name]: file.name,
        }));
      }
    } else if (type === "radio") {
      setVerificationData((prev) => ({
        ...prev,
        [name]: value,
      }));
    } else if (multiple) {
      const selectedOptions = Array.from(e.target.selectedOptions, option => option.value);
      setVerificationData((prev) => ({
        ...prev,
        [name]: selectedOptions,
      }));
    } else {
      setVerificationData((prev) => ({
        ...prev,
        [name]: value,
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!isAuthenticated) {
      if (!formData.name.trim()) {
        newErrors.name = "Name is required";
      }
      if (!formData.email.trim()) {
        newErrors.email = "Email is required";
      } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
        newErrors.email = "Email is invalid";
      }
      if (!formData.password) {
        newErrors.password = "Password is required";
      } else if (formData.password.length < 6) {
        newErrors.password = "Password must be at least 6 characters";
      }
      if (formData.password !== formData.password_confirmation) {
        newErrors.password_confirmation = "Passwords do not match";
      }
    }

    verificationForm.forEach((element, index) => {
      const fieldName = `element_${index}`;
      if (element.type === "file" && !verificationFiles[fieldName]) {
        newErrors[fieldName] = `${element.label} is required`;
      } else if ((element.type === "text" || element.type === "select") && !verificationData[fieldName]) {
        newErrors[fieldName] = `${element.label} is required`;
      } else if (element.type === "multi_select" && (!verificationData[fieldName] || verificationData[fieldName].length === 0)) {
        newErrors[fieldName] = `${element.label} is required`;
      } else if (element.type === "radio" && !verificationData[fieldName]) {
        newErrors[fieldName] = `${element.label} is required`;
      }
    });

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setServerErrors([]);
    
    if (!validateForm()) {
      return;
    }

    setSubmitting(true);

    const formDataToSend = new FormData();
    
    if (!isAuthenticated) {
      formDataToSend.append("name", formData.name);
      formDataToSend.append("email", formData.email);
      formDataToSend.append("password", formData.password);
      formDataToSend.append("password_confirmation", formData.password_confirmation);
      formDataToSend.append("is_guest", "true");
    }
    
    verificationForm.forEach((element, index) => {
      const fieldName = `element_${index}`;
      
      if (element.type === "file") {
        if (verificationFiles[fieldName]) {
          formDataToSend.append(fieldName, verificationFiles[fieldName]);
        }
      } else if (element.type === "multi_select") {
        if (verificationData[fieldName]) {
          verificationData[fieldName].forEach(value => {
            formDataToSend.append(`${fieldName}[]`, value);
          });
        }
      } else {
        if (verificationData[fieldName]) {
          formDataToSend.append(fieldName, verificationData[fieldName]);
        }
      }
    });

    try {
      const response = await applyForAffiliate(formDataToSend);
      
      if (response.result) {
        toast.success(response.message || "Application submitted");
        router.push("/");
      } else {
        setServerErrors([response.message || "Failed to submit application"]);
      }
    } catch (error) {
      console.error("Submission error:", error);
      if (error.message) {
        if (Array.isArray(error.message)) {
          setServerErrors(error.message);
        } else {
          setServerErrors([error.message]);
        }
      } else {
        setServerErrors(["Something went wrong. Please try again."]);
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <PageSpinner label="Loading application form" />;
  }

  return (
    <div className="container-page py-8 md:py-12">
      <div className="mx-auto max-w-2xl">
        <Breadcrumb
          items={[
            { name: "Home", href: "/" },
            { name: "Become an affiliate", href: "#" },
          ]}
        />

        <header className="mb-8 mt-8">
          <h1 className="type-title-1 text-ink">Become an affiliate</h1>
          <p className="type-callout mt-2 text-ink-tertiary">
            Share products you like and earn on every order placed through your
            link.
          </p>
        </header>

        {serverErrors.length > 0 && (
          <div
            role="alert"
            className="mb-6 flex gap-2.5 rounded-lg bg-danger-tint p-3.5 text-danger"
          >
            <AlertCircle size={17} className="mt-px shrink-0" aria-hidden="true" />
            <div className="type-footnote space-y-0.5">
              {serverErrors.map((error, index) => (
                <p key={index}>{error}</p>
              ))}
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {!isAuthenticated && (
            <div className="card-raised">
              <div className="border-b border-hairline px-6 py-4">
                <h2 className="type-headline text-ink">
                  User Info
                </h2>
              </div>
              <div className="p-6 space-y-4">
                <div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <FiUser className="h-[17px] w-[17px] text-ink-tertiary" />
                    </div>
                    <input
                      type="text"
                      name="name"
                      value={formData.name}
                      onChange={handleInputChange}
                      placeholder="Name"
                      className={`field pl-11 ${
                        errors.name ? "field-error" : ""
                      } rounded-md  text-sm`}
                    />
                  </div>
                  {errors.name && (
                    <p className="field-error-text">{errors.name}</p>
                  )}
                </div>

                <div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <FiMail className="h-[17px] w-[17px] text-ink-tertiary" />
                    </div>
                    <input
                      type="email"
                      name="email"
                      value={formData.email}
                      onChange={handleInputChange}
                      placeholder="Email"
                      className={`field pl-11 ${
                        errors.email ? "field-error" : ""
                      } rounded-md  text-sm`}
                    />
                  </div>
                  {errors.email && (
                    <p className="field-error-text">
                      {errors.email}
                    </p>
                  )}
                </div>

                <div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <FiLock className="h-[17px] w-[17px] text-ink-tertiary" />
                    </div>
                    <input
                      type={showPassword ? "text" : "password"}
                      name="password"
                      value={formData.password}
                      onChange={handleInputChange}
                      placeholder="Password"
                      className={`field pl-11 pr-11 ${
                        errors.password ? "field-error" : ""
                      } rounded-md  text-sm`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center"
                    >
                      {showPassword ? (
                        <FiEyeOff className="h-[17px] w-[17px] text-ink-tertiary hover:text-gray-600" />
                      ) : (
                        <FiEye className="h-[17px] w-[17px] text-ink-tertiary hover:text-gray-600" />
                      )}
                    </button>
                  </div>
                  {errors.password && (
                    <p className="field-error-text">
                      {errors.password}
                    </p>
                  )}
                </div>

                <div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <FiLock className="h-[17px] w-[17px] text-ink-tertiary" />
                    </div>
                    <input
                      type={showConfirmPassword ? "text" : "password"}
                      name="password_confirmation"
                      value={formData.password_confirmation}
                      onChange={handleInputChange}
                      placeholder="Confirm Password"
                      className="field pl-11 pr-11"
                    />
                    <button
                      type="button"
                      onClick={() =>
                        setShowConfirmPassword(!showConfirmPassword)
                      }
                      className="absolute inset-y-0 right-0 pr-3 flex items-center"
                    >
                      {showConfirmPassword ? (
                        <FiEyeOff className="h-[17px] w-[17px] text-ink-tertiary hover:text-gray-600" />
                      ) : (
                        <FiEye className="h-[17px] w-[17px] text-ink-tertiary hover:text-gray-600" />
                      )}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {verificationForm.length > 0 && (
            <div className="card-raised">
              <div className="border-b border-hairline px-6 py-4">
                <h2 className="type-headline text-ink">
                  Verification info
                </h2>
              </div>
              <div className="p-6 space-y-4">
                {verificationForm.map((element, index) => (
                  <div key={index}>
                    {element.type === "text" && (
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label className="md:col-span-1 field-label mb-0 self-center">
                          {element.label}{" "}
                          <span className="text-red-500">*</span>
                        </label>
                        <div className="md:col-span-3">
                          <input
                            type="text"
                            name={`element_${index}`}
                            placeholder={element.label}
                            value={verificationData[`element_${index}`] || ""}
                            onChange={handleVerificationInputChange}
                            required
                            className={`field ${
                              errors[`element_${index}`] ? "field-error" : ""
                            } rounded-md  text-sm`}
                          />
                          {errors[`element_${index}`] && (
                            <p className="field-error-text">
                              {errors[`element_${index}`]}
                            </p>
                          )}
                        </div>
                      </div>
                    )}

                    {element.type === "file" && (
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label className="md:col-span-1 field-label mb-0 self-center">
                          {element.label}
                        </label>
                        <div className="md:col-span-3">
                          <div className="relative">
                            <input
                              type="file"
                              id={`file-${index}`}
                              name={`element_${index}`}
                              onChange={handleVerificationInputChange}
                              className="hidden"
                              accept=".jpg,.jpeg,.png,.pdf"
                              required
                            />
                            <label
                              htmlFor={`file-${index}`}
                              className={`flex items-center justify-between w-full px-4 py-2.5 border ${
                                errors[`element_${index}`] ? "field-error" : ""
                              } rounded-md cursor-pointer hover:bg-gray-50 text-sm transition-colors duration-150`}
                            >
                              <span className={`truncate ${fileNames[`element_${index}`] ? 'text-ink' : 'text-ink-tertiary'}`}>
                                {fileNames[`element_${index}`] || "Choose file"}
                              </span>
                              <FiUpload className="h-[17px] w-[17px] text-ink-tertiary ml-2 flex-shrink-0" />
                            </label>
                          </div>
                          {errors[`element_${index}`] && (
                            <p className="field-error-text">
                              {errors[`element_${index}`]}
                            </p>
                          )}
                        </div>
                      </div>
                    )}

                    {element.type === "select" && element.options && (
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label className="md:col-span-1 field-label mb-0 self-center">
                          {element.label}
                        </label>
                        <div className="md:col-span-3">
                          <select
                            name={`element_${index}`}
                            value={verificationData[`element_${index}`] || ""}
                            onChange={handleVerificationInputChange}
                            required
                            className={`field ${
                              errors[`element_${index}`] ? "field-error" : ""
                            } rounded-md  text-sm`}
                          >
                            <option value="">Select {element.label}</option>
                            {typeof element.options === 'string' 
                              ? JSON.parse(element.options).map((option) => (
                                  <option key={option} value={option}>
                                    {option}
                                  </option>
                                ))
                              : element.options.map((option) => (
                                  <option key={option} value={option}>
                                    {option}
                                  </option>
                                ))
                            }
                          </select>
                          {errors[`element_${index}`] && (
                            <p className="field-error-text">
                              {errors[`element_${index}`]}
                            </p>
                          )}
                        </div>
                      </div>
                    )}

                    {element.type === "multi_select" && element.options && (
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label className="md:col-span-1 field-label mb-0 self-center">
                          {element.label}
                        </label>
                        <div className="md:col-span-3">
                          <select
                            name={`element_${index}`}
                            multiple
                            value={verificationData[`element_${index}`] || []}
                            onChange={handleVerificationInputChange}
                            required
                            className={`field ${
                              errors[`element_${index}`] ? "field-error" : ""
                            } rounded-md  text-sm min-h-[120px]`}
                          >
                            {typeof element.options === 'string' 
                              ? JSON.parse(element.options).map((option) => (
                                  <option key={option} value={option}>
                                    {option}
                                  </option>
                                ))
                              : element.options.map((option) => (
                                  <option key={option} value={option}>
                                    {option}
                                  </option>
                                ))
                            }
                          </select>
                          <p className="field-hint">
                            Hold Ctrl/Cmd to select multiple options
                          </p>
                          {errors[`element_${index}`] && (
                            <p className="field-error-text">
                              {errors[`element_${index}`]}
                            </p>
                          )}
                        </div>
                      </div>
                    )}

                    {element.type === "radio" && element.options && (
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <label className="md:col-span-1 field-label mb-0 self-center">
                          {element.label}
                        </label>
                        <div className="md:col-span-3">
                          <div className="flex flex-wrap gap-4">
                            {typeof element.options === 'string' 
                              ? JSON.parse(element.options).map((option) => (
                                  <label
                                    key={option}
                                    className="flex items-center gap-2 cursor-pointer"
                                  >
                                    <input
                                      type="radio"
                                      name={`element_${index}`}
                                      value={option}
                                      checked={verificationData[`element_${index}`] === option}
                                      onChange={handleVerificationInputChange}
                                      required
                                      className="h-4 w-4 accent-[var(--ink)]"
                                    />
                                    <span className="type-subhead text-ink-secondary">
                                      {option}
                                    </span>
                                  </label>
                                ))
                              : element.options.map((option) => (
                                  <label
                                    key={option}
                                    className="flex items-center gap-2 cursor-pointer"
                                  >
                                    <input
                                      type="radio"
                                      name={`element_${index}`}
                                      value={option}
                                      checked={verificationData[`element_${index}`] === option}
                                      onChange={handleVerificationInputChange}
                                      required
                                      className="h-4 w-4 accent-[var(--ink)]"
                                    />
                                    <span className="type-subhead text-ink-secondary">
                                      {option}
                                    </span>
                                  </label>
                                ))
                            }
                          </div>
                          {errors[`element_${index}`] && (
                            <p className="field-error-text">
                              {errors[`element_${index}`]}
                            </p>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="flex justify-end">
            <Button
              type="submit"
              size="lg"
              loading={submitting}
              className="w-full md:w-auto"
            >
              {submitting ? "Submitting" : "Submit application"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default BecomeAnAffiliate;