import React, { useState, useContext, ChangeEvent, FormEvent, useEffect } from "react";
import { AuthContext } from "../../context/AuthContext";
import axios, { AxiosError } from "axios";
import toast from "react-hot-toast";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card } from "@/components/ui/card";
import { Eye, EyeOff } from "lucide-react";
import { router } from "@inertiajs/react";

interface FormData {
  username: string;
  email: string;
  password: string;
}

const Login: React.FC = () => {
  const { login, API, user, isLoading } = useContext(AuthContext) as {
    login: (token: string, user: unknown) => void;
    API: string;
    user: any;
    isLoading: boolean;
  };

  const [isLogin, setIsLogin] = useState<boolean>(true);
  const [formData, setFormData] = useState<FormData>({
    username: "",
    email: "",
    password: "",
  });
  const [loading, setLoading] = useState<boolean>(false);
  const [showPassword, setShowPassword] = useState<boolean>(false);
  const [formErrors, setFormErrors] = useState<{[key: string]: string}>({});

  // Redirect to dashboard if user is already logged in
  useEffect(() => {
    if (!isLoading && user) {
      router.visit('/dashboard');
    }
  }, [user, isLoading]);

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
    
    // Clear error for this field when user starts typing
    if (formErrors[e.target.name]) {
      setFormErrors({
        ...formErrors,
        [e.target.name]: ''
      });
    }
  };

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);

    try {
        if (isLogin) {
          // 🔹 Login
          const response = await axios.post(`${API}/auth/login`, {
            username: formData.username,
            password: formData.password,
          }, {
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            }
          });
          
          if (response.data.success) {
            await login(response.data.access_token, response.data.user);
            toast.success(`🎉 Login berhasil! Selamat datang, ${response.data.user.name}!`);
            // Redirect to dashboard using Inertia
            setTimeout(() => {
              router.visit('/dashboard');
            }, 1000);
          } else {
            toast.error("Login gagal, coba lagi");
          }
        } else {
          // 🔹 Register
          // Ensure all required fields are present
          const registrationData = {
            username: formData.username.trim(),
            email: formData.email.trim(),
            password: formData.password
          };
          
          const response = await axios.post(`${API}/auth/register`, registrationData, {
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            }
          });
          
          if (response.data.success) {
            toast.success("🎉 Registrasi berhasil! Silakan login dengan akun yang baru dibuat.");
            
            // Clear form and switch to login tab
            setFormData({ username: "", email: "", password: "" });
            setFormErrors({});
            setIsLogin(true);
            
            // Show success message for login
            setTimeout(() => {
              toast.success("✨ Silakan login dengan username dan password yang baru dibuat");
            }, 1500);
          } else {
            toast.error("Registrasi gagal, coba lagi");
          }
        }
    } catch (err: unknown) {
      // Gunakan AxiosError supaya jelas tipenya
      if (err instanceof AxiosError) {
        
        // Handle validation errors (422)
        if (err.response?.status === 422) {
          const errors = err.response?.data?.errors;
          const message = err.response?.data?.message;
          
          if (errors) {
            // Set form errors for visual feedback
            const newErrors: {[key: string]: string} = {};
            Object.keys(errors).forEach(field => {
              const fieldName = field === 'username' ? 'Username' : 
                               field === 'email' ? 'Email' : 
                               field === 'password' ? 'Password' : field;
              newErrors[field] = errors[field][0];
              toast.error(`${fieldName}: ${errors[field][0]}`);
            });
            setFormErrors(newErrors);
          } else if (message) {
            // Handle specific messages like "username already taken"
            if (message.includes('username has already been taken')) {
              toast.error('Username sudah digunakan, coba username lain');
            } else if (message.includes('email has already been taken')) {
              toast.error('Email sudah digunakan, coba email lain');
            } else {
              toast.error(`${message}`);
            }
          } else {
            toast.error("Data tidak valid");
          }
        } else {
          // Handle login/registration errors
          if (err.response?.status === 401) {
            toast.error("Login gagal, coba lagi");
          } else if (err.response?.status === 500) {
            toast.error("Registrasi gagal, coba lagi");
          } else {
            const msg =
              err.response?.data?.message ||
              err.response?.data?.error ||
              err.response?.statusText ||
              "Terjadi kesalahan, coba lagi.";
            toast.error(`${msg}`);
          }
        }
      } else {
        toast.error("Terjadi kesalahan tak terduga");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-cyan-50 flex items-center justify-center p-4">
      <Card className="w-full max-w-md relative z-10 p-8 shadow-2xl border-0 bg-white/80 backdrop-blur-xl">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg">
            <svg
              className="w-8 h-8 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          </div>
          <h1 className="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            TrendBuss
          </h1>
          <p className="text-gray-600 mt-2">
            Dashboard Monitoring Bisnis Google Maps
          </p>
        </div>

        {/* Tabs */}
        <div className="flex bg-gray-100 rounded-xl p-1 mb-6">
          <button
            type="button"
            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-all duration-300 ${
              isLogin
                ? "bg-white text-blue-600 shadow-sm"
                : "text-gray-500 hover:text-gray-700"
            }`}
            onClick={() => {
              setIsLogin(true);
              setFormData({ username: "", email: "", password: "" });
              setFormErrors({});
            }}
          >
            Masuk
          </button>
          <button
            type="button"
            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-all duration-300 ${
              !isLogin
                ? "bg-white text-blue-600 shadow-sm"
                : "text-gray-500 hover:text-gray-700"
            }`}
            onClick={() => {
              setIsLogin(false);
              setFormData({ username: "", email: "", password: "" });
              setFormErrors({});
            }}
          >
            Daftar
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Username */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">Username</label>
            <Input
              type="text"
              name="username"
              value={formData.username}
              onChange={handleChange}
              placeholder="Masukkan username"
              required
              className={`h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400 ${
                formErrors.username ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''
              }`}
            />
            {formErrors.username && (
              <p className="text-red-500 text-xs mt-1">{formErrors.username}</p>
            )}
          </div>

          {/* Email (hanya untuk register) */}
          {!isLogin && (
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">Email</label>
              <Input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                placeholder="Masukkan email"
                required={!isLogin}
                className={`h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400 ${
                  formErrors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''
                }`}
              />
              {formErrors.email && (
                <p className="text-red-500 text-xs mt-1">{formErrors.email}</p>
              )}
            </div>
          )}

          {/* Password */}
          <div className="space-y-2 relative">
            <label className="text-sm font-medium text-gray-700">Password</label>
            <div className="relative">
              <Input
                type={showPassword ? "text" : "password"}
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder="Masukkan password"
                required
                className={`h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400 pr-10 ${
                  formErrors.password ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''
                }`}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
            </div>
            {formErrors.password && (
              <p className="text-red-500 text-xs mt-1">{formErrors.password}</p>
            )}
          </div>

          {/* Submit */}
          <Button
            type="submit"
            disabled={loading}
            className="w-full h-12 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? (
              <div className="flex items-center space-x-2">
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>{isLogin ? "Memproses login..." : "Memproses registrasi..."}</span>
              </div>
            ) : isLogin ? (
              "Masuk"
            ) : (
              "Daftar"
            )}
          </Button>
        </form>
        
      </Card>
    </div>
  );
};

export default Login;
